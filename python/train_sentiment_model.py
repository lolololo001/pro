import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.ensemble import RandomForestClassifier
import pickle
import re

# Load your dataset
# Adjust the path if needed
csv_path = '../parent/sentimental modal/parent_feedback_dataset.csv'
df = pd.read_csv(csv_path, names=['id', 'text', 'label', 'date', 'school_id'])

# Clean text function
def clean_text(text):
    text = re.sub(r'[^a-zA-Z\s]', '', str(text))
    text = text.lower()
    text = re.sub(r'\s+', ' ', text).strip()
    return text

df['clean_text'] = df['text'].apply(clean_text)

X = df['clean_text']
y = df['label']

# Vectorize text
vectorizer = TfidfVectorizer(max_features=1000)
X_vec = vectorizer.fit_transform(X)

# Train Random Forest
clf = RandomForestClassifier(n_estimators=100, random_state=42)
clf.fit(X_vec, y)

# Save model and vectorizer
with open('rf_model.pkl', 'wb') as f:
    pickle.dump(clf, f)
with open('vectorizer.pkl', 'wb') as f:
    pickle.dump(vectorizer, f)

print('Model and vectorizer saved as rf_model.pkl and vectorizer.pkl') 