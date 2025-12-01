#!/usr/bin/env python3
"""
Simple test script for sentiment analysis system
"""

import subprocess
import json
import sys
import os

def test_model_training():
    """Test if the model can be trained successfully"""
    print("Testing model training...")
    print("=" * 50)
    
    try:
        result = subprocess.run([
            'python', 'train_sentiment_model_simple.py'
        ], capture_output=True, text=True, cwd=os.path.dirname(__file__))
        
        if result.returncode == 0:
            print("✅ Model training successful!")
            print("Model saved as 'sentiment_model.pkl'")
            return True
        else:
            print("❌ Model training failed!")
            print(f"Error: {result.stderr}")
            return False
            
    except Exception as e:
        print(f"❌ Model training exception: {e}")
        return False

def test_sentiment_analysis():
    """Test the sentiment analysis with various inputs"""
    print("\nTesting sentiment analysis...")
    print("=" * 50)
    
    # Test cases with expected sentiment
    test_cases = [
        {
            'text': 'The teachers are very supportive and my child loves going to school.',
            'expected_sentiment': 'Positive'
        },
        {
            'text': 'The cafeteria food quality needs improvement.',
            'expected_sentiment': 'Negative'
        },
        {
            'text': 'I appreciate the regular updates on my child\'s progress.',
            'expected_sentiment': 'Positive'
        },
        {
            'text': 'There is too much emphasis on standardized testing.',
            'expected_sentiment': 'Negative'
        }
    ]
    
    for i, test_case in enumerate(test_cases, 1):
        print(f"\nTest {i}:")
        print(f"Input: {test_case['text']}")
        print(f"Expected: {test_case['expected_sentiment']}")
        
        try:
            # Call the sentiment analysis script
            result = subprocess.run([
                'python', 'sentiment_analysis_simple.py', 
                test_case['text']
            ], capture_output=True, text=True, cwd=os.path.dirname(__file__))
            
            if result.returncode == 0:
                try:
                    output = json.loads(result.stdout.strip())
                    print(f"Actual: {output.get('sentiment_label', 'Unknown')}")
                    print(f"Score: {output.get('sentiment_score', 'N/A')}")
                    print(f"Confidence: {output.get('confidence', 'N/A')}")
                    print(f"Suggestion: {output.get('suggestion', 'N/A')}")
                    
                    # Check if prediction matches expected
                    if output.get('sentiment_label') == test_case['expected_sentiment']:
                        print("✅ PASS")
                    else:
                        print("❌ FAIL - Sentiment mismatch")
                        
                except json.JSONDecodeError:
                    print("❌ FAIL - Invalid JSON output")
                    print(f"Output: {result.stdout}")
            else:
                print("❌ FAIL - Script execution failed")
                print(f"Error: {result.stderr}")
                
        except Exception as e:
            print(f"❌ FAIL - Exception: {str(e)}")
    
    print("\n" + "=" * 50)
    print("Testing completed!")

def main():
    """Main test function"""
    print("🚀 Testing Sentiment Analysis System")
    print("=" * 50)
    
    # First test model training
    if not test_model_training():
        print("❌ Model training failed. Cannot proceed with testing.")
        return False
    
    # Then test sentiment analysis
    test_sentiment_analysis()
    
    print("\n" + "=" * 50)
    print("🎉 All tests completed!")
    return True

if __name__ == "__main__":
    success = main()
    if not success:
        sys.exit(1) 