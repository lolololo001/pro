import json
import sys
import string

# Simple keyword-based sentiment analysis as fallback
POSITIVE_WORDS = {
    'good', 'great', 'excellent', 'amazing', 'wonderful', 'fantastic', 'outstanding',
    'supportive', 'helpful', 'friendly', 'caring', 'loving', 'enjoy', 'love', 'like',
    'appreciate', 'thank', 'grateful', 'satisfied', 'happy', 'pleased', 'impressed',
    'improved', 'better', 'best', 'excellent', 'superb', 'terrific', 'brilliant',
    'encouraging', 'motivating', 'inspiring', 'positive', 'constructive', 'valuable'
}

NEGATIVE_WORDS = {
    'bad', 'terrible', 'awful', 'horrible', 'disappointing', 'frustrating', 'annoying',
    'difficult', 'hard', 'challenging', 'problem', 'issue', 'concern', 'worry',
    'disappointed', 'unsatisfied', 'unhappy', 'angry', 'upset', 'frustrated',
    'confused', 'worried', 'anxious', 'stressful', 'overwhelming', 'difficult',
    'poor', 'weak', 'inadequate', 'insufficient', 'lacking', 'missing', 'absent',
    'slow', 'late', 'delayed', 'unreliable', 'inconsistent', 'unfair', 'unjust'
}

def simple_sentiment_analysis(text):
    """Simple keyword-based sentiment analysis"""
    text = text.lower()
    text = text.translate(str.maketrans('', '', string.punctuation))
    words = text.split()
    
    positive_count = sum(1 for word in words if word in POSITIVE_WORDS)
    negative_count = sum(1 for word in words if word in NEGATIVE_WORDS)
    
    if positive_count > negative_count:
        return 'Positive', 0.7 + (positive_count * 0.1)
    elif negative_count > positive_count:
        return 'Negative', 0.3 - (negative_count * 0.1)
    else:
        return 'Neutral', 0.5

def generate_suggestion(sentiment_label, feedback_text):
    """Generate appropriate suggestion based on sentiment"""
    if sentiment_label == 'Positive':
        return "Thank you for your positive feedback. We're glad to hear about your positive experience and will continue to maintain these high standards."
    else:
        return "We're sorry to hear about your concerns. We're committed to addressing these issues and will work on improving our services. Thank you for bringing this to our attention."

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
    
    try:
        # Try to use the trained model first
        import pickle
        with open('sentiment_model.pkl', 'rb') as f:
            model_data = pickle.load(f)
        
        model = model_data['model']
        vectorizer = model_data['vectorizer']
        label_encoder = model_data['label_encoder']
        
        # Simple preprocessing
        clean_text = text.lower().translate(str.maketrans('', '', string.punctuation))
        features = vectorizer.transform([clean_text])
        prediction = model.predict(features)[0]
        probabilities = model.predict_proba(features)[0]
        sentiment_label = label_encoder.inverse_transform([prediction])[0]
        confidence = max(probabilities)
        
        if sentiment_label == 'Positive':
            sentiment_score = confidence
        else:
            sentiment_score = 1 - confidence
            
    except Exception as e:
        # Fallback to simple keyword analysis
        sentiment_label, sentiment_score = simple_sentiment_analysis(text)
        confidence = abs(sentiment_score - 0.5) * 2  # Convert to 0-1 scale
    
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
    
    print(json.dumps(result))

if __name__ == "__main__":
    main() 