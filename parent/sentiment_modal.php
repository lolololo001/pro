<!-- Enhanced Sentiment Analysis Result Modal -->
<div id="sentimentModal" class="modal" style="display:none;">
    <div class="modal-content sentiment-modal" style="
        max-width: 600px; 
        border-radius: 20px; 
        padding: 0; 
        text-align: center;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        box-shadow: 0 25px 50px rgba(0,0,0,0.15);
        border: 1px solid rgba(0,112,74,0.1);
        overflow: hidden;
        position: relative;
    ">
        <!-- Modal Header -->
        <div class="modal-header sentiment-header" style="
            background: linear-gradient(135deg, #00704A 0%, #27ae60 100%);
            padding: 2.5rem 2rem 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        ">
            <div id="sentimentResultIcon" style="
                width: 80px;
                height: 80px;
                background: rgba(255,255,255,0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1.5rem;
                font-size: 2.5rem;
                backdrop-filter: blur(10px);
                border: 2px solid rgba(255,255,255,0.3);
                animation: iconPulse 2s ease-in-out infinite;
            "></div>
            <h2 id="sentimentResultLabel" style="
                margin: 0; 
                font-size: 1.8rem; 
                font-weight: 700;
                text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            "></h2>
            <p id="sentimentResultScore" style="
                margin: 0.5rem 0 0 0; 
                opacity: 0.9; 
                font-size: 1rem;
                font-weight: 300;
            "></p>
        </div>

        <!-- Modal Body -->
        <div class="modal-body" style="padding: 2rem;">
            <!-- Sentiment Score Bar -->
            <div class="sentiment-score-container" style="margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1rem; color: #333; font-weight: 600;">
                    <i class="fas fa-chart-line"></i> Sentiment Analysis Score
                </h4>
                <div class="sentiment-bar-container" style="
                    background: #f0f0f0;
                    border-radius: 25px;
                    height: 20px;
                    position: relative;
                    overflow: hidden;
                    margin-bottom: 0.5rem;
                ">
                    <div id="sentimentBar" style="
                        height: 100%;
                        border-radius: 25px;
                        transition: width 1s ease-in-out;
                        position: relative;
                    "></div>
                </div>
                <div class="sentiment-labels" style="
                    display: flex;
                    justify-content: space-between;
                    font-size: 0.8rem;
                    color: #666;
                    margin-top: 0.5rem;
                ">
                    <span>Negative</span>
                    <span>Neutral</span>
                    <span>Positive</span>
                </div>
            </div>

            <!-- Confidence Indicator -->
            <div class="confidence-container" style="margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1rem; color: #333; font-weight: 600;">
                    <i class="fas fa-bullseye"></i> Analysis Confidence
                </h4>
                <div class="confidence-circle" style="
                    width: 80px;
                    height: 80px;
                    border-radius: 50%;
                    margin: 0 auto;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: bold;
                    font-size: 1.2rem;
                    color: white;
                    position: relative;
                ">
                    <span id="confidenceText"></span>
                </div>
            </div>

            <!-- Suggestion Section -->
            <div class="suggestion-container" style="
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border-radius: 15px;
                padding: 1.5rem;
                border-left: 4px solid #00704A;
            ">
                <h4 style="margin-bottom: 1rem; color: #333; font-weight: 600;">
                    <i class="fas fa-lightbulb"></i> Our Response
                </h4>
                <div id="sentimentSuggestion" style="
                    color: #555;
                    line-height: 1.6;
                    font-size: 1rem;
                    text-align: left;
                "></div>
            </div>

            <!-- Action Buttons -->
            <div class="modal-actions" style="margin-top: 2rem;">
                <button class="btn btn-primary" onclick="closeSentimentModal()" style="
                    background: linear-gradient(135deg, #00704A 0%, #27ae60 100%);
                    border: none;
                    padding: 12px 30px;
                    border-radius: 25px;
                    color: white;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    box-shadow: 0 4px 15px rgba(0,112,74,0.3);
                ">
                    <i class="fas fa-check"></i> Got it!
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div id="loadingModal" class="modal" style="display:none;">
    <div class="modal-content loading-modal" style="
        max-width: 400px; 
        border-radius: 20px; 
        padding: 2rem; 
        text-align: center;
        background: white;
        box-shadow: 0 25px 50px rgba(0,0,0,0.15);
    ">
        <div class="loading-spinner" style="
            width: 60px;
            height: 60px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #00704A;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1.5rem;
        "></div>
        <h3 style="margin-bottom: 1rem; color: #333;">Analyzing Your Feedback</h3>
        <p style="color: #666; margin: 0;">Please wait while we process your message...</p>
    </div>
</div>

<style>
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@keyframes iconPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOutRight {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}

@keyframes statusUpdate {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
}

.modal-content {
    position: relative;
    margin: 5% auto;
    animation: slideInRight 0.3s ease-out;
}

.close {
    position: absolute;
    right: 20px;
    top: 20px;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: white;
    z-index: 1001;
    transition: all 0.3s ease;
}

.close:hover {
    color: #f0f0f0;
    transform: scale(1.1);
}

.btn {
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,112,74,0.4);
}

.sentiment-modal .modal-content {
    animation: slideInRight 0.4s ease-out;
}

.loading-modal .modal-content {
    animation: slideInRight 0.3s ease-out;
}
</style>

<script>
// Enhanced sentiment modal functions
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

function showLoadingModal() {
    document.getElementById('loadingModal').style.display = 'block';
}

function hideLoadingModal() {
    document.getElementById('loadingModal').style.display = 'none';
}

function closeSentimentModal() {
    document.getElementById('sentimentModal').style.display = 'none';
}

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    const sentimentModal = document.getElementById('sentimentModal');
    const loadingModal = document.getElementById('loadingModal');
    
    if (e.target === sentimentModal) {
        sentimentModal.style.display = 'none';
    }
    if (e.target === loadingModal) {
        loadingModal.style.display = 'none';
    }
});
</script> 