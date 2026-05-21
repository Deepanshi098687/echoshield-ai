import os
import pandas as pd
import nltk
import joblib

from sklearn.model_selection import train_test_split
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.pipeline import Pipeline
from sklearn.metrics import accuracy_score

# Get the script's directory and construct paths relative to it
script_dir = os.path.dirname(os.path.abspath(__file__))
dataset_path = os.path.join(script_dir, '..', 'dataset', 'hateXplain.csv')
model_output_path = os.path.join(script_dir, '..', 'trained_model', 'cyberbullying_model.pkl')

# Load dataset
df = pd.read_csv(dataset_path)

# Show columns
print(df.columns)

# Example:
# text column -> 'post_tokens'
# label column -> 'label'

# Convert token list to text if needed
df['text'] = df['post_tokens'].astype(str)

# Input and output
X = df['text']
y = df['label']

# Split dataset
X_train, X_test, y_train, y_test = train_test_split(
    X, y, test_size=0.2, random_state=42
)

# Create pipeline
model = Pipeline([
    ('tfidf', TfidfVectorizer()),
    ('classifier', LogisticRegression())
])

# Train model
model.fit(X_train, y_train)

# Predict
y_pred = model.predict(X_test)

# Accuracy
accuracy = accuracy_score(y_test, y_pred)

print("Accuracy:", accuracy)

# Create output directory if it doesn't exist
os.makedirs(os.path.dirname(model_output_path), exist_ok=True)

# Save model
joblib.dump(model, model_output_path)

print("Model Saved Successfully!")