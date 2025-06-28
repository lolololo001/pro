# Sentiment Analysis for Parent Feedback

This directory contains the sentiment analysis system for processing parent feedback in the school management system.

## Setup Instructions

### 1. Install Python Dependencies

```bash
pip install -r requirements.txt
```

### 2. Required Files

Make sure you have the following files in this directory:
- `sentiment_analysis.py` - Main sentiment analysis script
- `rf_model.pkl` - Trained Random Forest model (if available)
- `vectorizer.pkl` - Text vectorizer (if available)
- `requirements.txt` - Python dependencies

### 3. Test the System

Run the test script to verify everything works:

```bash
python test_sentiment.py
```

## How It Works

### Sentiment Analysis Process

1. **Text Cleaning**: Removes special characters and normalizes text
2. **Sentiment Scoring**: Uses TextBlob to analyze sentiment polarity
3. **Suggestion Generation**: Uses trained Random Forest model to generate suggestions
4. **Database Storage**: Stores results in `parent_feedback` table

### Database Schema

The system stores feedback in the `parent_feedback` table with these fields:
- `sentiment_score` (decimal 3,2): Sentiment score from 0-1
- `sentiment_label` (enum): 'positive', 'neutral', or 'negative'
- `suggestion` (text): AI-generated suggestion based on feedback

### Integration with PHP

The sentiment analysis is called from PHP using:

```php
$escapedFeedback = escapeshellarg($feedbackText);
$pythonScript = escapeshellcmd(__DIR__ . '/../python/sentiment_analysis.py');
$command = "python $pythonScript $escapedFeedback";
$output = shell_exec($command);
$result = json_decode($output, true);
```

## Features

- **Real-time Analysis**: Analyzes feedback as it's submitted
- **Fallback Handling**: Provides default values if analysis fails
- **Visual Feedback**: Shows sentiment results in a modal
- **Email Integration**: Sends confirmation emails with analysis results

## Troubleshooting

### Common Issues

1. **Python not found**: Ensure Python is installed and in PATH
2. **Missing dependencies**: Run `pip install -r requirements.txt`
3. **Model files missing**: System will use default suggestions
4. **Permission errors**: Ensure PHP can execute Python scripts

### Testing

Use the test script to verify functionality:

```bash
python test_sentiment.py
```

This will test with sample feedback and show sentiment scores, labels, and suggestions.

## Output Format

The script returns JSON with:
```json
{
  "sentiment_score": 0.75,
  "sentiment_label": "positive",
  "suggestion": "Thank you for your positive feedback...",
  "confidence": 0.85,
  "message": ""
}
``` 