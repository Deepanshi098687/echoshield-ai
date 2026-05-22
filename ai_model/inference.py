"""Shared hateXplain inference — used by Flask app and CLI for PHP."""

from __future__ import annotations

import json
import os
import re

import joblib

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH = os.path.join(BASE_DIR, 'trained_model', 'cyberbullying_model.pkl')
MAPPING_PATH = os.path.join(BASE_DIR, 'label_mapping.json')

FALLBACK_TOXIC = [
    'stupid', 'idiot', 'hate', 'ugly', 'loser', 'kill', 'moron', 'shut up',
    'pagal', 'bewakoof', 'ganda', 'bakwas', 'chup', 'nalayak',
]

MODEL_LABEL_BY_RISK = {
    'SAFE': 'normal',
    'MEDIUM': 'offensive',
    'HIGH': 'hatespeech',
}

DEFAULT_LABELS = {
    'SAFE': ['normal', '0', 'safe', 'nothate', 'non-offensive', 'non_offensive'],
    'MEDIUM': ['offensive', '1', 'medium', 'mild', 'abusive'],
    'HIGH': ['hate', 'hatespeech', 'hateful', '2', 'high', 'hate_speech'],
}

_model = None
_label_map = None


def load_label_map():
    global _label_map
    if _label_map is not None:
        return _label_map
    if os.path.isfile(MAPPING_PATH):
        with open(MAPPING_PATH, encoding='utf-8') as handle:
            _label_map = json.load(handle)
            return _label_map
    _label_map = DEFAULT_LABELS
    return _label_map


def load_model():
    global _model
    if _model is not None:
        return _model
    if os.path.isfile(MODEL_PATH):
        _model = joblib.load(MODEL_PATH)
    return _model


def normalize_token(value):
    if value is None:
        return ''
    text = str(value).strip().lower()
    text = re.sub(r'[\[\]\'"]', '', text)
    return text.replace('_', ' ').strip()


def map_label_to_risk(raw_label):
    token = normalize_token(raw_label)
    for risk, aliases in load_label_map().items():
        for alias in aliases:
            if token == normalize_token(alias):
                return risk.upper(), MODEL_LABEL_BY_RISK.get(risk.upper(), token)
    if token in ('normal', 'safe', 'nothate'):
        return 'SAFE', 'normal'
    if token in ('offensive', 'abusive', 'medium'):
        return 'MEDIUM', 'offensive'
    if token in ('hate', 'hatespeech', 'hateful', 'high'):
        return 'HIGH', 'hatespeech'
    return 'SAFE', 'normal'


def prepare_text(message):
    return re.sub(r'\s+', ' ', str(message).strip().lower())


def model_probabilities(model, text):
    if not hasattr(model, 'predict_proba'):
        return {}
    try:
        proba = model.predict_proba([text])[0]
        classifier = model.named_steps.get('classifier')
        if classifier is not None and hasattr(classifier, 'classes_'):
            classes = [normalize_token(c) for c in classifier.classes_]
            return {cls: round(float(p) * 100, 1) for cls, p in zip(classes, proba)}
    except Exception:
        pass
    return {}


def predict_with_model(message):
    model = load_model()
    if model is None:
        return None

    text = prepare_text(message)
    raw = model.predict([text])[0]
    risk, model_label = map_label_to_risk(raw)
    probabilities = model_probabilities(model, text)

    confidence = 0.0
    if probabilities:
        key = normalize_token(model_label)
        confidence = probabilities.get(key, max(probabilities.values()))
    elif hasattr(model, 'predict_proba'):
        try:
            confidence = round(float(max(model.predict_proba([text])[0])) * 100, 1)
        except Exception:
            confidence = 0.0

    toxicity_score = round(confidence / 100 * 2, 3) if risk == 'HIGH' else (
        round(confidence / 100, 3) if risk == 'MEDIUM' else round((100 - confidence) / 100 * 0.5, 3)
    )

    return {
        'message': text,
        'raw_prediction': normalize_token(raw),
        'model_label': model_label,
        'severity': risk,
        'risk_level': risk,
        'toxicity_score': toxicity_score,
        'confidence': confidence,
        'probabilities': probabilities,
        'detected_words': [],
        'engine': 'hateXplain-ml',
    }


def predict_fallback(message):
    lower = prepare_text(message)
    detected = [word for word in FALLBACK_TOXIC if word in lower]
    score = float(len(detected))

    if score == 0:
        risk, model_label = 'SAFE', 'normal'
    elif score <= 2:
        risk, model_label = 'MEDIUM', 'offensive'
    else:
        risk, model_label = 'HIGH', 'hatespeech'

    return {
        'message': lower,
        'raw_prediction': model_label,
        'model_label': model_label,
        'severity': risk,
        'risk_level': risk,
        'toxicity_score': score,
        'confidence': min(99.0, 76.0 + score * 4),
        'probabilities': {},
        'detected_words': detected,
        'engine': 'keyword-fallback',
    }


def predict(message):
    message = str(message).strip()
    if not message:
        raise ValueError('message is required')
    result = predict_with_model(message)
    if result is None:
        result = predict_fallback(message)
    return result
