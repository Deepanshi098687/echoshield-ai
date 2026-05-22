from flask import Flask, jsonify, request

from inference import load_meta, load_model, predict

try:
    from flask_cors import CORS
except ImportError:
    CORS = None

app = Flask(__name__)
if CORS is not None:
    CORS(app)


@app.route('/health', methods=['GET'])
def health():
    meta = load_meta()
    return jsonify({
        'status': 'ok',
        'model_loaded': load_model() is not None,
        'dataset': meta.get('dataset', 'hateXplain.csv'),
    })


def load_meta():
    import json
    import os
    path = os.path.join(os.path.dirname(__file__), 'model_meta.json')
    if os.path.isfile(path):
        with open(path, encoding='utf-8') as handle:
            return json.load(handle)
    return {'dataset': 'hateXplain.csv', 'model': 'TF-IDF + LogisticRegression'}


@app.route('/predict', methods=['POST'])
def predict_route():
    data = request.get_json(silent=True) or {}
    message = str(data.get('message', '')).strip()
    if not message:
        return jsonify({'error': 'message is required'}), 400
    return jsonify(predict(message))


if __name__ == '__main__':
    app.run(debug=False, host='0.0.0.0', port=5000)
