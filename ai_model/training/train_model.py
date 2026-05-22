import json
import os

import joblib
import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import accuracy_score
from sklearn.model_selection import train_test_split
from sklearn.pipeline import Pipeline

script_dir = os.path.dirname(os.path.abspath(__file__))
dataset_path = os.path.join(script_dir, '..', 'dataset', 'hateXplain.csv')
model_output_path = os.path.join(script_dir, '..', 'trained_model', 'cyberbullying_model.pkl')
meta_output_path = os.path.join(script_dir, '..', 'model_meta.json')

df = pd.read_csv(dataset_path)
print(df.columns)

df['text'] = df['post_tokens'].astype(str)
X = df['text']
y = df['label']

X_train, X_test, y_train, y_test = train_test_split(
    X, y, test_size=0.2, random_state=42
)

model = Pipeline([
    ('tfidf', TfidfVectorizer(max_features=20000, ngram_range=(1, 2))),
    ('classifier', LogisticRegression(max_iter=1000)),
])

model.fit(X_train, y_train)
y_pred = model.predict(X_test)
accuracy = accuracy_score(y_test, y_pred)

print('Accuracy:', accuracy)

os.makedirs(os.path.dirname(model_output_path), exist_ok=True)
joblib.dump(model, model_output_path)

classes = []
if hasattr(model.named_steps['classifier'], 'classes_'):
    classes = [str(c) for c in model.named_steps['classifier'].classes_]

meta = {
    'dataset': 'hateXplain.csv',
    'model': 'TF-IDF + LogisticRegression',
    'labels': ['normal', 'offensive', 'hatespeech'],
    'classes': classes,
    'accuracy': round(float(accuracy), 4),
    'accuracy_percent': round(float(accuracy) * 100, 1),
    'train_samples': int(len(X_train)),
    'test_samples': int(len(X_test)),
}

with open(meta_output_path, 'w', encoding='utf-8') as handle:
    json.dump(meta, handle, indent=2)

print('Model Saved Successfully!')
print('Meta saved to', meta_output_path)
