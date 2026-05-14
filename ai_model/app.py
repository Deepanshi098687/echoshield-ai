from flask import Flask, request, jsonify

import importlib.util

cors_available = False
CORS = None

if importlib.util.find_spec('flask_cors') is not None:
    flask_cors = importlib.import_module('flask_cors')
    CORS = flask_cors.CORS
    cors_available = True

app = Flask(__name__)
if cors_available:
    CORS(app)

# Sample toxic words
toxic_words = [
    "stupid",
    "idiot",
    "hate",
    "ugly",
    "loser",
    "kill",
    "moron",
    "shut up"
]

toxic_words.extend([
    "pagal",
    "bewakoof",
    "ganda",
    "bakwas",
    "chup",
    "nalayak"
])

@app.route('/predict', methods=['POST'])

def predict():

    data = request.json

    message = data['message'].lower()

    toxicity_score = 0

    detected_words = []

    for word in toxic_words:

        if word in message:

            toxicity_score += 1
            detected_words.append(word)

    # Severity Logic

    if toxicity_score == 0:
        severity = "SAFE"

    elif toxicity_score <= 2:
        severity = "MEDIUM"

    else:
        severity = "HIGH"

    return jsonify({

        "message": message,
        "toxicity_score": toxicity_score,
        "severity": severity,
        "detected_words": detected_words

    })

if __name__ == '__main__':
    app.run(debug=False, host='0.0.0.0', port=5000)