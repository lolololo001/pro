document.addEventListener('DOMContentLoaded', function() {
    var feedbackForm = document.getElementById('feedbackForm');
    if (feedbackForm) {
        feedbackForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = this.querySelector('button[type="submit"]');
            var responseDiv = document.getElementById('feedbackResponse');
            if (!responseDiv) {
                responseDiv = document.createElement('div');
                responseDiv.id = 'feedbackResponse';
                feedbackForm.appendChild(responseDiv);
            }

            btn.disabled = true;
            btn.classList.add('loading');
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Submitting...';

            var form = e.target;
            var formData = new FormData(form);
            formData.append('submit_feedback', '1');

            fetch('submit_feedback.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    // Not valid JSON, show raw response
                    btn.disabled = false;
                    btn.classList.remove('loading');
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Feedback';
                    responseDiv.innerHTML = `
                        <div class="alert alert-danger mt-3" style="border-radius: 12px;">
                            <i class="fas fa-exclamation-circle"></i> <strong>Server returned invalid response:</strong><br><pre style="white-space:pre-wrap;">${text}</pre>
                        </div>
                    `;
                    return;
                }
                btn.disabled = false;
                btn.classList.remove('loading');
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Feedback';

                if (data.success) {
                    // Create response message with sentiment and suggestion
                    var messageClass = data.sentiment === 'positive' ? 'success' : 'warning';
                    var icon = data.sentiment === 'positive' ? 'smile' : 'lightbulb';
                    var title = data.sentiment === 'positive' ? 'Thank you for your positive feedback!' : 'Thank you for your feedback';
                    
                    responseDiv.innerHTML = `
                        <div class="alert alert-${messageClass} mt-3" style="border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                            <h5 class="alert-heading" style="color: var(--primary-dark);"><i class="fas fa-${icon}"></i> ${title}</h5>
                            <p class="mb-2" style="color: var(--text);">${data.message}</p>
                            <div class="mt-2">
                                <span class="badge bg-${messageClass}" style="margin-right: 8px;">Sentiment: ${data.sentiment}</span>
                                <span class="badge bg-info">Category: ${data.category}</span>
                            </div>
                        </div>
                    `;
                    form.reset();
                } else {
                    responseDiv.innerHTML = `
                        <div class="alert alert-danger mt-3" style="border-radius: 12px;">
                            <i class="fas fa-exclamation-circle"></i> ${data.message || 'Unknown error.'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.classList.remove('loading');
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Feedback';
                responseDiv.innerHTML = `
                    <div class="alert alert-danger mt-3">
                        <i class="fas fa-exclamation-circle"></i> <strong>AJAX error:</strong> ${error}
                    </div>
                `;
                console.error('Error:', error);
            });
        });
    }
});