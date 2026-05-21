import os
import joblib

# Get the script's directory and construct the model path
script_dir = os.path.dirname(os.path.abspath(__file__))
model_path = os.path.join(script_dir, '..', 'trained_model', 'cyberbullying_model.pkl')

# Load the model
model = joblib.load(model_path)

text = ["You are disgusting"]

prediction = model.predict(text)

print("Prediction:", prediction)