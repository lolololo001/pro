import pandas as pd
import numpy as np
import string
import pickle
import json
import sys

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

def analyze_sentiment(text):
    """Analyze sentiment of the given text"""
    try:
        # Load the trained model
        with open('sentiment_model.pkl', 'rb') as f:
            model_data = pickle.load(f)
        
        model = model_data['model']
        vectorizer = model_data['vectorizer']
        label_encoder = model_data['label_encoder']
        
        # Preprocess the input text
        clean_text = preprocess_text(text)
        
        # Vectorize the text
        features = vectorizer.transform([clean_text])
        
        # Make prediction
        prediction = model.predict(features)[0]
        probabilities = model.predict_proba(features)[0]
        
        # Get sentiment label and confidence
        sentiment_label = label_encoder.inverse_transform([prediction])[0]
        confidence = max(probabilities)
        
        # Calculate sentiment score (0-1 scale)
        if sentiment_label == 'Positive':
            sentiment_score = confidence
        else:
            sentiment_score = 1 - confidence
        
        # Generate suggestion
        suggestion = generate_suggestion(sentiment_label, text)
        
        # Return results
        result = {
            'sentiment_label': sentiment_label,
            'sentiment_score': round(sentiment_score, 3),
            'confidence': round(confidence, 3),
            'suggestion': suggestion,
            'success': True
        }
        
        return result
        
    except Exception as e:
        # Fallback response if model fails
        return {
            'sentiment_label': 'Neutral',
            'sentiment_score': 0.5,
            'confidence': 0.5,
            'suggestion': 'Thank you for your feedback. We will review and address your concerns.',
            'success': False,
            'error': str(e)
        }

def main():
    """Main function to handle command line input"""
    if len(sys.argv) < 2:
        print(json.dumps({
            'error': 'No text provided',
            'success': False
        }))
        return
    
    # Get text from command line argument
    text = sys.argv[1]
    
    # Analyze sentiment
    result = analyze_sentiment(text)
    
    # Output JSON result
    print(json.dumps(result))

if __name__ == "__main__":
    main() 