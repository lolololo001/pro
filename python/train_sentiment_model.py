import pandas as pd
import numpy as np
from sklearn.model_selection import train_test_split
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.ensemble import RandomForestClassifier
import pickle
import os
import re

def clean_text(text):
    text = str(text).lower()
    text = re.sub(r'[^a-z\s]', '', text)
    text = re.sub(r'\s+', ' ', text).strip()
    return text

def create_suggestion_mapping(df):
    # Create enhanced suggestion mapping with better categorization
    suggestion_map = {}

    for category in df['category'].unique():
        category_data = df[df['category'] == category]

        # Get suggestions with their frequencies and sentiment distribution
        suggestions = category_data['suggested_action'].value_counts()

        # Calculate sentiment scores for each suggestion
        suggestion_scores = {}
        for suggestion in suggestions.index:
            suggestion_data = category_data[category_data['suggested_action'] == suggestion]
            pos_ratio = len(suggestion_data[suggestion_data['sentiment'] == 'positive']) / len(suggestion_data)
            neg_ratio = len(suggestion_data[suggestion_data['sentiment'] == 'negative']) / len(suggestion_data)

            # Score based on frequency and sentiment balance
            score = suggestions[suggestion] * (1 + abs(pos_ratio - neg_ratio))
            suggestion_scores[suggestion] = score

        # Sort suggestions by score and take top 15 for better variety
        sorted_suggestions = sorted(suggestion_scores.items(), key=lambda x: x[1], reverse=True)
        suggestion_map[category] = [s[0] for s in sorted_suggestions[:15]]

    # Add specific food-related suggestions for facilities category
    if 'facilities' in suggestion_map:
        food_suggestions = [
            "Improve cafeteria menu and food quality standards",
            "Review and enhance nutritional value of school meals",
            "Conduct regular food quality inspections and monitoring",
            "Upgrade kitchen equipment and food preparation facilities",
            "Implement student feedback system for meal preferences",
            "Partner with local suppliers for fresher food options",
            "Provide more healthy and diverse meal choices",
            "Train cafeteria staff in food safety and quality standards"
        ]
        # Add food suggestions to facilities if not already present
        for suggestion in food_suggestions:
            if suggestion not in suggestion_map['facilities']:
                suggestion_map['facilities'].append(suggestion)

    return suggestion_map

def train_model(data_path):
    # Read and preprocess data
    df = pd.read_csv(data_path)
    df['feedback_text'] = df['feedback_text'].apply(clean_text)
    
    # Convert sentiment to numeric
    sentiment_map = {'positive': 1, 'negative': -1}
    df = df[df['sentiment'].isin(['positive', 'negative'])]
    df['sentiment_numeric'] = df['sentiment'].map(sentiment_map)
    
    # Split features and target
    X = df['feedback_text']
    y = df['sentiment_numeric']
    
    # Create train-test split
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)
    
    # Create and fit TF-IDF vectorizer with enhanced parameters
    vectorizer = TfidfVectorizer(
        ngram_range=(1, 3),
        min_df=2,
        max_df=0.95,
        max_features=5000,
        stop_words='english'
    )
    X_train_vectorized = vectorizer.fit_transform(X_train)
    X_test_vectorized = vectorizer.transform(X_test)
    
    # Train Random Forest with optimized parameters
    model = RandomForestClassifier(
        n_estimators=200,
        max_depth=20,
        min_samples_split=5,
        min_samples_leaf=2,
        class_weight='balanced',
        random_state=42
    )
    model.fit(X_train_vectorized, y_train)
    
    # Create suggestion mapping
    suggestion_map = create_suggestion_mapping(df)
    
    # Save model and suggestion map together
    model_path = os.path.join(os.path.dirname(data_path), '..', 'rf_model.pkl')
    with open(model_path, 'wb') as f:
        pickle.dump((model, suggestion_map), f)
    
    # Save vectorizer separately
    vectorizer_path = os.path.join(os.path.dirname(data_path), '..', 'vectorizer.pkl')
    with open(vectorizer_path, 'wb') as f:
        pickle.dump(vectorizer, f)
    
    # Print model performance
    train_accuracy = model.score(X_train_vectorized, y_train)
    test_accuracy = model.score(X_test_vectorized, y_test)
    print(f'Training Accuracy: {train_accuracy:.2f}')
    print(f'Testing Accuracy: {test_accuracy:.2f}')
    
    # Print detailed metrics
    from sklearn.metrics import classification_report
    y_pred = model.predict(X_test_vectorized)
    print('\nClassification Report:')
    print(classification_report(y_test, y_pred, target_names=['Negative', 'Positive']))

if __name__ == '__main__':
    # Get the absolute path to the dataset
    script_dir = os.path.dirname(os.path.abspath(__file__))
    data_path = os.path.join(script_dir, '..', 'parents_feedback-analysis', 'school_feedback_suggestions_dataset.csv')
    
    if not os.path.exists(data_path):
        print(f'Error: Dataset not found at {data_path}')
        exit(1)
    
    train_model(data_path)