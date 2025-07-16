import sys
import pickle
import os
import json
import numpy as np
from sklearn.feature_extraction.text import TfidfVectorizer
import re
from sklearn.metrics.pairwise import cosine_similarity

def clean_text(text):
    text = str(text).lower()
    text = re.sub(r'[^a-z\s]', '', text)
    text = re.sub(r'\s+', ' ', text).strip()
    return text

def load_model_and_vectorizer():
    model_path = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'rf_model.pkl')
    vectorizer_path = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'vectorizer.pkl')
    
    # Load the model and suggestion map
    with open(model_path, 'rb') as f:
        model, suggestion_map = pickle.load(f)
    
    # Load the vectorizer
    with open(vectorizer_path, 'rb') as f:
        vectorizer = pickle.load(f)
    
    return model, vectorizer, suggestion_map

def get_feedback_category(text, category_keywords, vectorizer):
    # Clean and transform the text
    cleaned_text = clean_text(text)
    text_vector = vectorizer.transform([cleaned_text])
    
    # Calculate weighted scores for each category
    category_scores = {}
    for category, keywords in category_keywords.items():
        # Clean and join keywords
        category_text = ' '.join(keywords)
        category_vector = vectorizer.transform([category_text])
        
        # Calculate cosine similarity
        similarity = cosine_similarity(text_vector, category_vector)[0][0]
        category_scores[category] = float(similarity)
    
    # Return category with highest similarity
    return max(category_scores.items(), key=lambda x: x[1])[0]

def get_best_suggestion(feedback_text, suggestions, sentiment_label):
    # Clean the feedback text
    cleaned_feedback = clean_text(feedback_text)
    feedback_terms = cleaned_feedback.split()

    # Score suggestions based on relevance to feedback
    suggestion_scores = []
    for suggestion in suggestions:
        suggestion_terms = clean_text(suggestion).split()

        # Count matching terms
        matching_terms = len(set(feedback_terms) & set(suggestion_terms))

        # Context-specific scoring for food/cafeteria issues
        context_score = 0
        food_keywords = ['food', 'cafeteria', 'lunch', 'meal', 'eat', 'nutrition', 'menu', 'kitchen', 'dining']
        if any(word in feedback_terms for word in food_keywords):
            if 'cafeteria' in suggestion_terms or 'food' in suggestion_terms or 'menu' in suggestion_terms:
                context_score += 5

        # Quality/condition keywords
        quality_keywords = ['dirty', 'bad', 'poor', 'terrible', 'awful', 'disgusting', 'unacceptable']
        if any(word in feedback_terms for word in quality_keywords):
            if 'improve' in suggestion_terms or 'quality' in suggestion_terms or 'standards' in suggestion_terms:
                context_score += 3

        # Check for sentiment-appropriate words
        sentiment_score = 0
        if sentiment_label == 'negative':
            action_words = ['implement', 'improve', 'create', 'develop', 'establish', 'provide', 'enhance', 'update', 'fix', 'address', 'resolve']
            sentiment_score = sum(1 for word in suggestion_terms if word in action_words)
        elif sentiment_label == 'positive':
            support_words = ['continue', 'maintain', 'support', 'expand', 'recognize', 'celebrate', 'strengthen', 'keep']
            sentiment_score = sum(1 for word in suggestion_terms if word in support_words)

        # Calculate final score with weighted components
        score = (matching_terms * 2) + (context_score * 3) + (sentiment_score * 2)
        suggestion_scores.append((suggestion, score))

    # Return the suggestion with highest score, or a contextual fallback
    if suggestion_scores:
        best_suggestion = max(suggestion_scores, key=lambda x: x[1])[0]

        # If no good match found and it's food-related, provide specific food suggestion
        if max(suggestion_scores, key=lambda x: x[1])[1] == 0:
            food_keywords = ['food', 'cafeteria', 'lunch', 'meal', 'eat', 'nutrition', 'menu', 'kitchen', 'dining']
            if any(word in feedback_terms for word in food_keywords):
                return "We will review and improve our cafeteria menu and food quality standards to better meet student needs."

        return best_suggestion
    else:
        return f"Thank you for your {sentiment_label} feedback. We will review and take appropriate action."

def analyze_sentiment(feedback_text):
    try:
        # Load model and vectorizer
        model, vectorizer, suggestion_map = load_model_and_vectorizer()
        
        # Clean and transform the input text
        cleaned_text = clean_text(feedback_text)
        text_vectorized = vectorizer.transform([cleaned_text])
        
        # Get prediction probabilities
        probabilities = model.predict_proba(text_vectorized)[0]
        confidence_score = max(probabilities)
        prediction = model.predict(text_vectorized)[0]
        
        # Map sentiment with adjusted thresholds
        if confidence_score < 0.55:  # Lower threshold for more decisive predictions
            sentiment_label = 'neutral'
        else:
            sentiment_label = 'positive' if prediction == 1 else 'negative'
        
        # Enhanced category keywords with more specific terms and better food/cafeteria coverage
        category_keywords = {
            'academics': ['learning', 'curriculum', 'teacher', 'study', 'grade', 'academic', 'class', 'subject',
                        'homework', 'assignment', 'test', 'exam', 'education', 'lesson', 'teaching', 'course',
                        'student', 'progress', 'achievement', 'improvement', 'skills', 'math', 'reading',
                        'science', 'performance', 'understanding', 'tutoring', 'instruction'],
            'administration': ['principal', 'staff', 'policy', 'management', 'administrative', 'office',
                              'administration', 'schedule', 'planning', 'organization', 'leadership', 'decision',
                              'coordinator', 'supervisor', 'administrator', 'enrollment', 'budget', 'hiring',
                              'procedures', 'registration'],
            'communication': ['contact', 'inform', 'notification', 'message', 'update', 'email', 'phone',
                             'communicate', 'newsletter', 'announcement', 'notice', 'feedback', 'response',
                             'information', 'communication', 'website', 'portal', 'updates', 'informed'],
            'extracurricular': ['activity', 'club', 'sport', 'program', 'event', 'team', 'practice',
                                'competition', 'game', 'tournament', 'performance', 'play', 'music',
                                'art', 'drama', 'dance', 'enrichment', 'robotics', 'field trip', 'trips'],
            'facilities': ['building', 'classroom', 'equipment', 'maintenance', 'facility', 'infrastructure',
                          'playground', 'library', 'cafeteria', 'gym', 'laboratory', 'resource', 'bathroom',
                          'field', 'court', 'parking', 'condition', 'repair', 'safety', 'food', 'lunch',
                          'meal', 'dining', 'kitchen', 'menu', 'eat', 'eating', 'nutrition', 'healthy',
                          'dirty', 'clean', 'hvac', 'air conditioning', 'water fountain', 'overcrowded'],
            'behavior': ['discipline', 'conduct', 'behavior', 'attitude', 'bullying', 'respect',
                         'responsibility', 'rule', 'safety', 'supervision', 'interaction', 'manner',
                         'character', 'value', 'ethic', 'environment', 'climate', 'recess', 'conflict',
                         'aggressive', 'unsafe', 'mediation']
        }
        
        # Get feedback category
        category = get_feedback_category(cleaned_text, category_keywords, vectorizer)
        
        # Get relevant suggestions based on sentiment and category
        if category in suggestion_map and suggestion_map[category]:
            suggestions = suggestion_map[category]
            suggestion = get_best_suggestion(feedback_text, suggestions, sentiment_label)
        else:
            suggestion = f"Thank you for your {sentiment_label} feedback about {category}. We will review and take appropriate action."
        
        # Prepare the response
        result = {
            'sentiment_score': float(confidence_score),
            'sentiment_label': sentiment_label,
            'category': category,
            'suggestion': suggestion
        }
        
        print(json.dumps(result))
        return 0
        
    except Exception as e:
        print(json.dumps({
            'error': str(e),
            'sentiment_score': 0.5,
            'sentiment_label': 'neutral',
            'category': 'general',
            'suggestion': 'Thank you for your feedback. We will review and address your concerns.'
        }))
        return 1

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print(json.dumps({
            'error': 'No feedback text provided',
            'sentiment_score': 0.5,
            'sentiment_label': 'neutral',
            'category': 'general',
            'suggestion': 'Please provide your feedback text.'
        }))
        sys.exit(1)
    
    feedback_text = sys.argv[1]
    sys.exit(analyze_sentiment(feedback_text))