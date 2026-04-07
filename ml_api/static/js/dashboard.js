document.addEventListener('DOMContentLoaded', () => {
    // Upload Handler
    const uploadBtn = document.getElementById('uploadBtn');
    if (uploadBtn) {
        uploadBtn.addEventListener('click', async () => {
            const fileInput = document.getElementById('csvFile');
            const status = document.getElementById('uploadStatus');

            if (fileInput.files.length === 0) {
                alert('Please select a file first.');
                return;
            }

            const formData = new FormData();
            formData.append('file', fileInput.files[0]);

            status.textContent = 'Uploading...';

            try {
                const response = await fetch('/api/upload', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (response.ok) {
                    status.textContent = 'Upload successful!';
                    status.className = 'text-success';
                } else {
                    status.textContent = 'Error: ' + data.error;
                    status.className = 'text-danger';
                }
            } catch (e) {
                status.textContent = 'Upload failed.';
                status.className = 'text-danger';
            }
        });
    }

    // Train Handler
    const trainBtn = document.getElementById('trainBtn');
    if (trainBtn) {
        trainBtn.addEventListener('click', async () => {
            const loader = document.getElementById('trainLoader');
            const metricsSection = document.getElementById('metricsSection');

            if (!confirm('This will retrain all models on the current dataset. Continue?')) {
                return;
            }

            loader.style.display = 'block';
            trainBtn.disabled = true;
            metricsSection.style.display = 'none';

            try {
                const response = await fetch('/api/train', {
                    method: 'POST'
                });
                const data = await response.json();

                loader.style.display = 'none';
                trainBtn.disabled = false;

                if (response.ok) {
                    alert('Training completed successfully!');
                    displayMetrics(data.metrics);
                } else {
                    alert('Training failed: ' + data.error);
                }
            } catch (e) {
                loader.style.display = 'none';
                trainBtn.disabled = false;
                alert('Connection error during training.');
            }
        });
    }
});

function displayMetrics(metrics) {
    const metricsSection = document.getElementById('metricsSection');
    metricsSection.style.display = 'block';

    // Summary Cards
    document.getElementById('acc_lr').textContent = (metrics.logistic_regression.accuracy * 100).toFixed(1) + '%';
    document.getElementById('acc_svm').textContent = (metrics.linear_svm.accuracy * 100).toFixed(1) + '%';
    document.getElementById('acc_dt').textContent = (metrics.decision_tree.accuracy * 100).toFixed(1) + '%';
    document.getElementById('acc_ens').textContent = (metrics.ensemble.accuracy * 100).toFixed(1) + '%';

    // Table
    const tbody = document.querySelector('#detailedMetricsTable tbody');
    tbody.innerHTML = '';

    for (const [algo, data] of Object.entries(metrics)) {
        const row = document.createElement('tr');
        const niceName = algo.replace('_', ' ').toUpperCase();
        const cm = data.confusion_matrix;
        // cm is [[TN, FP], [FN, TP]]
        const cmStr = `TN:${cm[0][0]}, FP:${cm[0][1]}, FN:${cm[1][0]}, TP:${cm[1][1]}`;

        row.innerHTML = `
            <td>${niceName}</td>
            <td>${(data.accuracy * 100).toFixed(2)}%</td>
            <td>${cmStr}</td>
        `;
        tbody.appendChild(row);
    }
}
