#!/usr/bin/env python3
"""
Simple setup script for sentiment analysis system (Windows compatible)
"""

import subprocess
import sys
import os

def train_model():
    """Train the sentiment analysis model"""
    print("Training sentiment analysis model...")
    print("=" * 50)
    
    try:
        result = subprocess.run([
            'python', 'train_sentiment_model_simple.py'
        ], capture_output=True, text=True, cwd=os.path.dirname(__file__))
        
        if result.returncode == 0:
            print("✅ Model trained successfully!")
            print("Model saved as 'sentiment_model.pkl'")
            return True
        else:
            print("❌ Model training failed!")
            print(f"Error: {result.stderr}")
            return False
            
    except Exception as e:
        print(f"❌ Model training exception: {e}")
        return False

def test_system():
    """Test the sentiment analysis system"""
    print("\nTesting sentiment analysis system...")
    print("=" * 50)
    
    test_cases = [
        {
            'text': 'The teachers are very supportive and my child loves going to school.',
            'expected': 'Positive'
        },
        {
            'text': 'The cafeteria food quality needs improvement.',
            'expected': 'Negative'
        }
    ]
    
    for i, test_case in enumerate(test_cases, 1):
        print(f"\nTest {i}: {test_case['expected']} sentiment")
        
        try:
            result = subprocess.run([
                'python', 'sentiment_analysis_simple.py', 
                test_case['text']
            ], capture_output=True, text=True, cwd=os.path.dirname(__file__))
            
            if result.returncode == 0:
                import json
                output = json.loads(result.stdout.strip())
                actual = output.get('sentiment_label', 'Unknown')
                confidence = output.get('confidence', 0)
                
                print(f"Expected: {test_case['expected']}")
                print(f"Actual: {actual}")
                print(f"Confidence: {confidence:.3f}")
                
                if actual == test_case['expected']:
                    print("✅ PASS")
                else:
                    print("❌ FAIL")
            else:
                print("❌ FAIL - Script execution failed")
                print(f"Error: {result.stderr}")
                
        except Exception as e:
            print(f"❌ FAIL - Exception: {str(e)}")
    
    print("\n" + "=" * 50)
    print("Testing completed!")

def main():
    """Main setup function"""
    print("🚀 Setting up Sentiment Analysis System")
    print("=" * 50)
    
    # Check if we're in the right directory
    if not os.path.exists('train_sentiment_model_simple.py'):
        print("❌ Error: train_sentiment_model_simple.py not found. Please run this script from the python directory.")
        return False
    
    # Train model
    if not train_model():
        return False
    
    # Test system
    test_system()
    
    print("\n" + "=" * 50)
    print("🎉 Setup completed successfully!")
    print("\nThe sentiment analysis system is now ready to use.")
    print("You can now submit feedback through the dashboard and see sentiment analysis results.")
    
    return True

if __name__ == "__main__":
    success = main()
    if not success:
        sys.exit(1) 