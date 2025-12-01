import pandas as pd
import numpy as np
import string
import pickle
import json
import sys
from sklearn.preprocessing import LabelEncoder
from sklearn.model_selection import train_test_split
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import classification_report, accuracy_score

# Simple stopwords list (no NLTK download required)
STOPWORDS = {
    'i', 'me', 'my', 'myself', 'we', 'our', 'ours', 'ourselves', 'you', "you're", "you've", "you'll", "you'd", 
    'your', 'yours', 'yourself', 'yourselves', 'he', 'him', 'his', 'himself', 'she', "she's", 'her', 'hers', 
    'herself', 'it', "it's", 'its', 'itself', 'they', 'them', 'their', 'theirs', 'themselves', 'what', 'which', 
    'who', 'whom', 'this', 'that', "that'll", 'these', 'those', 'am', 'is', 'are', 'was', 'were', 'be', 'been', 
    'being', 'have', 'has', 'had', 'having', 'do', 'does', 'did', 'doing', 'a', 'an', 'the', 'and', 'but', 'if', 
    'or', 'because', 'as', 'until', 'while', 'of', 'at', 'by', 'for', 'with', 'against', 'between', 'into', 
    'through', 'during', 'before', 'after', 'above', 'below', 'to', 'from', 'up', 'down', 'in', 'out', 'on', 'off', 
    'over', 'under', 'again', 'further', 'then', 'once', 'here', 'there', 'when', 'where', 'why', 'how', 'all', 
    'any', 'both', 'each', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 
    'same', 'so', 'than', 'too', 'very', 's', 't', 'can', 'will', 'just', 'don', "don't", 'should', "should've", 
    'now', 'd', 'll', 'm', 'o', 're', 've', 'y', 'ain', 'aren', "aren't", 'couldn', "couldn't", 'didn', "didn't", 
    'doesn', "doesn't", 'hadn', "hadn't", 'hasn', "hasn't", 'haven', "haven't", 'isn', "isn't", 'ma', 'mightn', 
    "mightn't", 'mustn', "mustn't", 'needn', "needn't", 'shan', "shan't", 'shouldn', "shouldn't", 'wasn', "wasn't", 
    'weren', "weren't", 'won', "won't", 'wouldn', "wouldn't"
}

def preprocess_text(text):
    """Simple text preprocessing without NLTK dependencies"""
    text = str(text).lower()  # Lowercase
    text = text.translate(str.maketrans('', '', string.punctuation))  # Remove punctuation
    tokens = [word for word in text.split() if word not in STOPWORDS and len(word) > 2]  # Remove stopwords and short words
    return ' '.join(tokens)

def generate_suggestion(sentiment_label, feedback_text):
    """Generate appropriate suggestion based on sentiment"""
    if sentiment_label == 'Positive':
        return "Thank you for your positive feedback. We're glad to hear about your positive experience and will continue to maintain these high standards."
    else:
        return "We're sorry to hear about your concerns. We're committed to addressing these issues and will work on improving our services. Thank you for bringing this to our attention."

def train_model():
    """Train the Random Forest sentiment analysis model"""
    try:
        # Load the dataset
        dataset_path = '../parent/sentimental/parent_feedback_dataset.csv'
        df = pd.read_csv(dataset_path)
        
        print(f"Loaded dataset with {len(df)} samples")
        print(f"Sentiment distribution: {df['Sentiment_Label'].value_counts().to_dict()}")
        
        # Preprocess the text
        print("Preprocessing text...")
        df['clean_feedback'] = df['Parent_Feedback'].apply(preprocess_text)
        
        # Encode sentiment labels
        le = LabelEncoder()
        df['sentiment_encoded'] = le.fit_transform(df['Sentiment_Label'])
        
        print(f"Encoded classes: {le.classes_}")
        
        # Remove any rows with invalid sentiment labels
        df = df[df['Sentiment_Label'].isin(['Positive', 'Negative'])]
        print(f"After filtering: {len(df)} samples")
        
        # Split dataset
        X = df['clean_feedback']
        y = df['sentiment_encoded']
        
        X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)
        
        print(f"Training samples: {len(X_train)}")
        print(f"Testing samples: {len(X_test)}")
        
        # Vectorize text data
        print("Vectorizing text...")
        vectorizer = TfidfVectorizer(max_features=3000, ngram_range=(1, 2))
        X_train_tfidf = vectorizer.fit_transform(X_train)
        X_test_tfidf = vectorizer.transform(X_test)
        
        print(f"Number of features: {X_train_tfidf.shape[1]}")
        
        # Train Random Forest model
        print("Training Random Forest model...")
        rf_model = RandomForestClassifier(
            n_estimators=50,  # Reduced for faster training
            max_depth=8,
            min_samples_split=10,
            min_samples_leaf=4,
            random_state=42,
            n_jobs=-1
        )
        
        rf_model.fit(X_train_tfidf, y_train)
        
        # Evaluate model
        y_pred = rf_model.predict(X_test_tfidf)
        accuracy = accuracy_score(y_test, y_pred)
        
        print(f"Model accuracy: {accuracy:.4f}")
        print("\nClassification Report:")
        print(classification_report(y_test, y_pred, target_names=['Negative', 'Positive']))
        
        # Save the model and vectorizer
        model_data = {
            'model': rf_model,
            'vectorizer': vectorizer,
            'label_encoder': le,
            'accuracy': accuracy
        }
        
        with open('sentiment_model.pkl', 'wb') as f:
            pickle.dump(model_data, f)
        
        print("Model saved successfully!")
        
        # Test with a few examples
        test_examples = [
            "The teachers are very supportive and my child loves going to school.",
            "The cafeteria food quality needs improvement.",
            "I appreciate the regular updates on my child's progress.",
            "There is too much emphasis on standardized testing."
        ]
        
        print("\nTesting with examples:")
        for example in test_examples:
            clean_text = preprocess_text(example)
            features = vectorizer.transform([clean_text])
            prediction = rf_model.predict(features)[0]
            probability = rf_model.predict_proba(features)[0]
            sentiment = le.inverse_transform([prediction])[0]
            confidence = max(probability)
            
            print(f"Text: {example}")
            print(f"Sentiment: {sentiment} (confidence: {confidence:.3f})")
            print(f"Suggestion: {generate_suggestion(sentiment, example)}")
            print("-" * 50)
        
        return True
        
    except Exception as e:
        print(f"Error training model: {str(e)}")
        return False

if __name__ == "__main__":
    success = train_model()
    if success:
        print("Model training completed successfully!")
    else:
        print("Model training failed!")
        sys.exit(1) 