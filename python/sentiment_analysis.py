"""
Suggestion-Based Feedback Analysis Script

This script uses a trained Random Forest model to predict the suggested action for a given feedback text.
Usage:
    python sentiment_analysis.py "Text to analyze"
Returns:
    JSON string with the predicted suggestion, sentiment score, and sentiment label
"""

import sys
import json
import re
import pickle
import os
import numpy as np
from textblob import TextBlob

def clean_text(text):
    """
    Clean and preprocess text for analysis
    """
    text = re.sub(r'[^a-zA-Z\s]', '', str(text))
    text = text.lower()
    text = re.sub(r'\s+', ' ', text).strip()
    return text

def get_sentiment_score(text):
    """
    Get sentiment score using TextBlob
    """
    try:
        blob = TextBlob(text)
        # TextBlob returns polarity between -1 (negative) and 1 (positive)
        polarity = blob.sentiment.polarity
        
        # Convert to 0-1 scale for database storage
        sentiment_score = (polarity + 1) / 2
        
        # Determine sentiment label
        if polarity > 0.1:
            sentiment_label = 'positive'
        elif polarity < -0.1:
            sentiment_label = 'negative'
        else:
            sentiment_label = 'neutral'
            
        return sentiment_score, sentiment_label
    except Exception as e:
        # Fallback values if sentiment analysis fails
        return 0.5, 'neutral'

def predict_suggestion(text):
    """
    Predict the suggested action for the given feedback text using the trained model
    """
    model_path = os.path.join(os.path.dirname(__file__), 'rf_model.pkl')
    vectorizer_path = os.path.join(os.path.dirname(__file__), 'vectorizer.pkl')

    try:
        with open(model_path, 'rb') as f:
            model = pickle.load(f)
        with open(vectorizer_path, 'rb') as f:
            vectorizer = pickle.load(f)
    except FileNotFoundError:
        # Return default suggestion if model files not found
        return {
            "suggestion": "Thank you for your feedback. We will review and address your concerns.",
            "confidence": 0.0,
            "message": "Model files not found. Using default suggestion."
        }

    cleaned_text = clean_text(text)
    if not cleaned_text:
        return {
            "suggestion": "Thank you for your feedback. We will review and address your concerns.",
            "confidence": 0.0,
            "message": "No meaningful text provided for analysis."
        }

    try:
        X_vec = vectorizer.transform([cleaned_text])
        suggestion = model.predict(X_vec)[0]
        proba = model.predict_proba(X_vec)[0]
        confidence = float(np.max(proba))

        # Format suggestion
        if isinstance(suggestion, bytes):
            suggestion = suggestion.decode('utf-8')
        suggestion_str = str(suggestion).strip()

        return {
            "suggestion": suggestion_str,
            "confidence": round(confidence, 3)
        }
    except Exception as e:
        # Return default suggestion if prediction fails
        return {
            "suggestion": "Thank you for your feedback. We will review and address your concerns.",
            "confidence": 0.0,
            "message": f"Prediction failed: {str(e)}"
        }

def analyze_feedback(text):
    """
    Complete feedback analysis including sentiment and suggestion
    """
    # Get sentiment analysis
    sentiment_score, sentiment_label = get_sentiment_score(text)
    
    # Get suggestion from model
    suggestion_result = predict_suggestion(text)
    
    return {
        "sentiment_score": round(sentiment_score, 2),
        "sentiment_label": sentiment_label,
        "suggestion": suggestion_result["suggestion"],
        "confidence": suggestion_result["confidence"],
        "message": suggestion_result.get("message", "")
    }

def save_to_database(feedback_text, suggestion):
    """
    Save the feedback and suggestion to the database (example using pymysql)
    You must update DB credentials and table/column names as per your setup.
    """
    try:
        import pymysql
        conn = pymysql.connect(host='localhost', user='root', password='', db='your_db')
        cursor = conn.cursor()
        sql = "INSERT INTO feedback (feedback_text, suggested_action) VALUES (%s, %s)"
        cursor.execute(sql, (feedback_text, suggestion))
        conn.commit()
        cursor.close()
        conn.close()
        return True
    except Exception as e:
        return False

def main():
    if len(sys.argv) < 2:
        print(json.dumps({
            "error": "No text provided for analysis",
            "usage": "python sentiment_analysis.py 'Text to analyze'"
        }))
        sys.exit(1)

    text = sys.argv[1]
    result = analyze_feedback(text)

    # Optionally save to database if prediction is successful
    if result.get("suggestion"):
        save_status = save_to_database(text, result["suggestion"])
        result["saved_to_db"] = save_status

    print(json.dumps(result, indent=2))

if __name__ == "__main__":
    main()