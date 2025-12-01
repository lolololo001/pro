// Enhanced Feedback Form Handler
document.addEventListener('DOMContentLoaded', function() {
    const feedbackForm = document.getElementById('feedbackForm');
    const submitButton = document.getElementById('submitButton');
    const responseDiv = document.getElementById('feedbackResponse');
    
    if (feedbackForm) {
        feedbackForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            showLoadingModal();
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            // Clear previous responses
            responseDiv.innerHTML = '';
            
            // Get form data
            const formData = new FormData(feedbackForm);
            
            // Validate form
            const subject = formData.get('subject').trim();
            const feedbackType = formData.get('feedback_type');
            const message = formData.get('message').trim();
            
            if (!subject || !feedbackType || !message) {
                hideLoadingModal();
                showError('Please fill in all required fields.');
                resetSubmitButton();
                return;
            }
            
            // Submit form via AJAX
            fetch('process_feedback.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoadingModal();
                
                if (data.success) {
                    // Show success message
                    showSuccess(data.message);
                    
                    // Show sentiment analysis modal
                    if (data.sentiment_data) {
                        setTimeout(() => {
                            showSentimentModal(data.sentiment_data);
                        }, 500);
                    }
                    
                    // Reset form
                    feedbackForm.reset();
                    
                    // Update form labels
                    updateFormLabels();
                    
                } else {
                    showError(data.error || 'An error occurred while submitting feedback.');
                }
            })
            .catch(error => {
                hideLoadingModal();
                console.error('Error:', error);
                showError('Network error. Please try again.');
            })
            .finally(() => {
                resetSubmitButton();
            });
        });
    }
    
    // Form validation and real-time feedback
    const formInputs = feedbackForm?.querySelectorAll('input, select, textarea');
    if (formInputs) {
        formInputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                clearFieldError(this);
            });
        });
    }
    
    // Character counter for message textarea
    const messageTextarea = document.getElementById('message');
    if (messageTextarea) {
        const charCounter = document.createElement('div');
        charCounter.className = 'char-counter';
        charCounter.style.cssText = `
            font-size: 0.8rem;
            color: #666;
            text-align: right;
            margin-top: 0.25rem;
        `;
        messageTextarea.parentNode.appendChild(charCounter);
        
        messageTextarea.addEventListener('input', function() {
            const remaining = 1000 - this.value.length;
            charCounter.textContent = `${remaining} characters remaining`;
            charCounter.style.color = remaining < 100 ? '#dc3545' : '#666';
        });
    }
});

// Helper functions
function showLoadingModal() {
    const loadingModal = document.getElementById('loadingModal');
    if (loadingModal) {
        loadingModal.style.display = 'block';
    }
}

function hideLoadingModal() {
    const loadingModal = document.getElementById('loadingModal');
    if (loadingModal) {
        loadingModal.style.display = 'none';
    }
}

function showSuccess(message) {
    const responseDiv = document.getElementById('feedbackResponse');
    responseDiv.innerHTML = `
        <div class="alert alert-success" style="
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            animation: slideInDown 0.3s ease-out;
        ">
            <i class="fas fa-check-circle"></i> ${message}
        </div>
    `;
}

function showError(message) {
    const responseDiv = document.getElementById('feedbackResponse');
    responseDiv.innerHTML = `
        <div class="alert alert-danger" style="
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            animation: slideInDown 0.3s ease-out;
        ">
            <i class="fas fa-exclamation-circle"></i> ${message}
        </div>
    `;
}

function resetSubmitButton() {
    const submitButton = document.getElementById('submitButton');
    if (submitButton) {
        submitButton.disabled = false;
        submitButton.innerHTML = `
            <i class="fas fa-paper-plane"></i>
            Submit Feedback
            <span class="btn-hover-effect"></span>
        `;
    }
}

function validateField(field) {
    const value = field.value.trim();
    const fieldName = field.name;
    
    if (!value) {
        showFieldError(field, `${getFieldDisplayName(fieldName)} is required.`);
        return false;
    }
    
    // Additional validation for specific fields
    if (fieldName === 'message' && value.length < 10) {
        showFieldError(field, 'Message must be at least 10 characters long.');
        return false;
    }
    
    if (fieldName === 'subject' && value.length < 3) {
        showFieldError(field, 'Subject must be at least 3 characters long.');
        return false;
    }
    
    clearFieldError(field);
    return true;
}

function showFieldError(field, message) {
    field.style.borderColor = '#dc3545';
    field.classList.add('error');
    
    // Remove existing error message
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    
    // Add new error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.style.cssText = `
        color: #dc3545;
        font-size: 0.8rem;
        margin-top: 0.25rem;
        animation: slideInDown 0.2s ease-out;
    `;
    errorDiv.textContent = message;
    field.parentNode.appendChild(errorDiv);
}

function clearFieldError(field) {
    field.style.borderColor = '';
    field.classList.remove('error');
    
    const errorDiv = field.parentNode.querySelector('.field-error');
    if (errorDiv) {
        errorDiv.remove();
    }
}

function getFieldDisplayName(fieldName) {
    const fieldNames = {
        'subject': 'Subject',
        'feedback_type': 'Feedback Type',
        'message': 'Message'
    };
    return fieldNames[fieldName] || fieldName;
}

function updateFormLabels() {
    const formLabels = document.querySelectorAll('.form-label');
    formLabels.forEach(label => {
        if (label.classList.contains('active')) {
            label.classList.remove('active');
        }
    });
}

// Enhanced sentiment modal function
function showSentimentModal(sentimentData) {
    const modal = document.getElementById('sentimentModal');
    const icon = document.getElementById('sentimentResultIcon');
    const label = document.getElementById('sentimentResultLabel');
    const score = document.getElementById('sentimentResultScore');
    const bar = document.getElementById('sentimentBar');
    const confidenceText = document.getElementById('confidenceText');
    const confidenceCircle = document.querySelector('.confidence-circle');
    const suggestion = document.getElementById('sentimentSuggestion');
    
    // Set sentiment icon and colors
    if (sentimentData.sentiment_label === 'Positive') {
        icon.innerHTML = '<i class="fas fa-smile"></i>';
        icon.style.background = 'rgba(39, 174, 96, 0.2)';
        icon.style.borderColor = 'rgba(39, 174, 96, 0.5)';
        label.textContent = 'Positive Feedback';
        bar.style.background = 'linear-gradient(90deg, #27ae60, #2ecc71)';
        confidenceCircle.style.background = 'linear-gradient(135deg, #27ae60, #2ecc71)';
    } else if (sentimentData.sentiment_label === 'Negative') {
        icon.innerHTML = '<i class="fas fa-frown"></i>';
        icon.style.background = 'rgba(231, 76, 60, 0.2)';
        icon.style.borderColor = 'rgba(231, 76, 60, 0.5)';
        label.textContent = 'Negative Feedback';
        bar.style.background = 'linear-gradient(90deg, #e74c3c, #c0392b)';
        confidenceCircle.style.background = 'linear-gradient(135deg, #e74c3c, #c0392b)';
    } else {
        icon.innerHTML = '<i class="fas fa-meh"></i>';
        icon.style.background = 'rgba(149, 165, 166, 0.2)';
        icon.style.borderColor = 'rgba(149, 165, 166, 0.5)';
        label.textContent = 'Neutral Feedback';
        bar.style.background = 'linear-gradient(90deg, #95a5a6, #7f8c8d)';
        confidenceCircle.style.background = 'linear-gradient(135deg, #95a5a6, #7f8c8d)';
    }
    
    // Set score text
    score.textContent = `Sentiment Score: ${(sentimentData.sentiment_score * 100).toFixed(1)}%`;
    
    // Animate sentiment bar
    const barWidth = sentimentData.sentiment_score * 100;
    bar.style.width = '0%';
    setTimeout(() => {
        bar.style.width = barWidth + '%';
    }, 100);
    
    // Set confidence
    const confidencePercent = (sentimentData.confidence * 100).toFixed(1);
    confidenceText.textContent = confidencePercent + '%';
    
    // Set suggestion
    suggestion.textContent = sentimentData.suggestion;
    
    // Show modal
    modal.style.display = 'block';
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
@keyframes slideInDown {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.field-error {
    animation: slideInDown 0.2s ease-out;
}
`;
document.head.appendChild(style); 