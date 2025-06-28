#!/usr/bin/env python3
"""
Test script for sentiment analysis
"""

import subprocess
import json
import sys

def test_sentiment_analysis():
    """Test the sentiment analysis with sample feedback"""
    
    test_feedback = [
        "The teachers are excellent and my child is learning so much!",
        "The school facilities are terrible and need immediate improvement.",
        "The communication between school and parents is okay but could be better."
    ]
    
    print("Testing Sentiment Analysis...")
    print("=" * 50)
    
    for i, feedback in enumerate(test_feedback, 1):
        print(f"\nTest {i}: {feedback}")
        print("-" * 30)
        
        try:
            # Run the sentiment analysis script
            result = subprocess.run([
                sys.executable, 'sentiment_analysis.py', feedback
            ], capture_output=True, text=True, cwd='.')
            
            if result.returncode == 0:
                data = json.loads(result.stdout)
                print(f"Sentiment Score: {data.get('sentiment_score', 'N/A')}")
                print(f"Sentiment Label: {data.get('sentiment_label', 'N/A')}")
                print(f"Suggestion: {data.get('suggestion', 'N/A')}")
                print(f"Confidence: {data.get('confidence', 'N/A')}")
            else:
                print(f"Error: {result.stderr}")
                
        except Exception as e:
            print(f"Exception: {e}")

if __name__ == "__main__":
    test_sentiment_analysis() 