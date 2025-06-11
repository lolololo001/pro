#!/usr/bin/env python3
"""
Sentiment Analysis Script for SchoolComm

This script analyzes the sentiment of text input and returns a sentiment score and label.
It uses the NLTK and TextBlob libraries for sentiment analysis.

Usage:
    python sentiment_analysis.py "Text to analyze"

Returns:
    JSON string with sentiment score and label
"""

import sys
import json
import re
from textblob import TextBlob

def clean_text(text):
    """
    Clean and preprocess text for sentiment analysis
    """
    # Remove special characters and numbers
    text = re.sub(r'[^a-zA-Z\s]', '', text)
    # Convert to lowercase
    text = text.lower()
    # Remove extra whitespace
    text = re.sub(r'\s+', ' ', text).strip()
    return text

def analyze_sentiment(text):
    """
    Analyze sentiment of text using TextBlob
    
    Returns:
        dict: Dictionary with sentiment score and label
    """
    # Clean the text
    cleaned_text = clean_text(text)
    
    # Skip empty text
    if not cleaned_text:
        return {
            "score": 0.0,
            "label": "neutral"
        }
    
    # Create TextBlob object
    blob = TextBlob(cleaned_text)
    
    # Get polarity score (-1 to 1)
    polarity = blob.sentiment.polarity
    
    # Normalize to 0-1 scale
    normalized_score = (polarity + 1) / 2
    
    # Determine sentiment label
    if polarity > 0.1:
        label = "positive"
    elif polarity < -0.1:
        label = "negative"
    else:
        label = "neutral"
    
    return {
        "score": round(normalized_score, 2),
        "label": label
    }

def main():
    """
    Main function to process command line arguments and return sentiment analysis
    """
    # Check if text argument is provided
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No text provided for analysis"}))
        sys.exit(1)
    
    # Get text from command line argument
    text = sys.argv[1]
    
    # Analyze sentiment
    result = analyze_sentiment(text)
    
    # Return result as JSON
    print(json.dumps(result))

if __name__ == "__main__":
    main()