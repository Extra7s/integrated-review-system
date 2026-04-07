document.addEventListener('DOMContentLoaded', () => {
    const predictBtn = document.getElementById('predictBtn');

    if (predictBtn) {
        predictBtn.addEventListener('click', async () => {
            const text = document.getElementById('reviewText').value;
            const algo = document.getElementById('algorithm').value;
            const resultBox = document.getElementById('resultBox');
            const loader = document.getElementById('loader');

            if (!text.trim()) {
                alert('Please enter a review text.');
                return;
            }

            // UI Reset
            resultBox.style.display = 'none';
            resultBox.className = 'result-box'; // reset classes
            loader.style.display = 'block';

            try {
                const response = await fetch('/api/predict', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        review: text,
                        algorithm: algo
                    })
                });

                const data = await response.json();

                loader.style.display = 'none';

                if (response.ok) {
                    resultBox.style.display = 'block';
                    document.getElementById('predictionResult').textContent = data.result;
                    document.getElementById('confidenceScore').textContent = data.confidence;
                    document.getElementById('latency').textContent = data.latency;

                    if (data.result === 'Fake') {
                        resultBox.classList.add('fake');
                    } else {
                        resultBox.classList.add('genuine');
                    }
                } else {
                    alert(data.error || 'An error occurred.');
                }

            } catch (error) {
                loader.style.display = 'none';
                console.error('Error:', error);
                alert('Failed to connect to the server.');
            }
        });
    }
    // Analyze URL Handler
    const analyzeUrlBtn = document.getElementById('analyzeUrlBtn');
    if (analyzeUrlBtn) {
        analyzeUrlBtn.addEventListener('click', async () => {
            const urlInput = document.getElementById('urlInput');
            const url = urlInput.value.trim();
            const loader = document.getElementById('urlLoader');
            const resultsSection = document.getElementById('urlResultsSection');

            if (!url) {
                alert('Please enter a URL.');
                return;
            }

            loader.style.display = 'block';
            analyzeUrlBtn.disabled = true;
            resultsSection.style.display = 'none';

            try {
                const response = await fetch('/api/analyze_url', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ url: url })
                });
                const data = await response.json();

                loader.style.display = 'none';
                analyzeUrlBtn.disabled = false;

                if (response.ok) {
                    displayUrlResults(data);
                } else {
                    alert('Analysis failed: ' + data.error);
                }
            } catch (e) {
                loader.style.display = 'none';
                analyzeUrlBtn.disabled = false;
                alert('Connection error during analysis.');
            }
        });
    }
});

function displayUrlResults(data) {
    const resultsSection = document.getElementById('urlResultsSection');
    resultsSection.style.display = 'block';

    document.getElementById('urlFakePercent').textContent = data.fake_percentage;
    document.getElementById('urlRecommendation').textContent = data.summary_recommendation;
    document.getElementById('urlTotalReviews').textContent = data.total_reviews;

    // Color coding
    // Color coding
    const recElem = document.getElementById('urlRecommendation');
    recElem.className = ''; // reset classes
    if (data.summary_recommendation.includes("High Risk")) {
        recElem.classList.add('text-danger');
        recElem.style.fontWeight = 'bold';
    } else if (data.summary_recommendation.includes("Caution")) {
        recElem.classList.add('text-warning');
        recElem.style.fontWeight = 'bold';
    } else {
        recElem.classList.add('text-success');
        recElem.style.fontWeight = 'bold';
    }

    const tbody = document.querySelector('#urlReviewsTable tbody');
    tbody.innerHTML = '';

    data.reviews.forEach(review => {
        const row = document.createElement('tr');
        const colorClass = review.result === 'Fake' ? 'text-danger' : 'text-success';
        row.innerHTML = `
            <td>${review.text}</td>
            <td class="${colorClass}" style="font-weight: bold;">${review.result}</td>
            <td>${review.confidence}</td>
        `;
        tbody.appendChild(row);
    });
}
