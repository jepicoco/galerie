// Test de configuration email
document.getElementById('test-email-config')?.addEventListener('click', async function() {
    this.disabled = true;
    this.textContent = '🔄 Test en cours...';
    
    try {
        const response = await fetch('order_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=test_email_config'
        });
        
        const result = await response.json();
        const resultDiv = document.getElementById('email-test-result');
        
        resultDiv.style.display = 'block';
        if (result.success) {
            resultDiv.className = 'test-result success';
            resultDiv.innerHTML = '✅ ' + result.message;
        } else {
            resultDiv.className = 'test-result error';
            resultDiv.innerHTML = '❌ ' + result.error;
        }
        
    } catch (error) {
        const resultDiv = document.getElementById('email-test-result');
        resultDiv.style.display = 'block';
        resultDiv.className = 'test-result error';
        resultDiv.innerHTML = '❌ Erreur de communication: ' + error.message;
    }
    
    this.disabled = false;
    this.textContent = '🧪 Tester la configuration';
});

// Test d'envoi d'email
document.getElementById('send-test-email')?.addEventListener('click', async function() {
    this.disabled = true;
    this.textContent = '📤 Envoi en cours...';
    
    try {
        const response = await fetch('order_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=send_test_email'
        });
        
        const result = await response.json();
        const resultDiv = document.getElementById('email-test-result');
        
        resultDiv.style.display = 'block';
        if (result.success) {
            resultDiv.className = 'test-result success';
            resultDiv.innerHTML = '✅ ' + result.message + '<br><small>Référence: ' + result.reference + '</small>';
        } else {
            resultDiv.className = 'test-result error';
            resultDiv.innerHTML = '❌ ' + result.error;
        }
        
    } catch (error) {
        const resultDiv = document.getElementById('email-test-result');
        resultDiv.style.display = 'block';
        resultDiv.className = 'test-result error';
        resultDiv.innerHTML = '❌ Erreur de communication: ' + error.message;
    }
    
    this.disabled = false;
    this.textContent = '📧 Envoyer un email de test';
});

// Diagnostic système
document.getElementById('run-diagnostic')?.addEventListener('click', async function() {
    this.disabled = true;
    this.textContent = '🔄 Diagnostic en cours...';
    
    try {
        const response = await fetch('diagnostic.php');
        const resultDiv = document.getElementById('diagnostic-result');
        
        if (response.ok) {
            resultDiv.style.display = 'block';
            resultDiv.className = 'test-result success';
            resultDiv.innerHTML = '✅ Diagnostic terminé. <a href="diagnostic.php" target="_blank">Voir le rapport complet</a>';
        } else {
            resultDiv.style.display = 'block';
            resultDiv.className = 'test-result error';
            resultDiv.innerHTML = '❌ Erreur lors du diagnostic';
        }
        
    } catch (error) {
        const resultDiv = document.getElementById('diagnostic-result');
        resultDiv.style.display = 'block';
        resultDiv.className = 'test-result error';
        resultDiv.innerHTML = '❌ Erreur: ' + error.message;
    }
    
    this.disabled = false;
    this.textContent = '🔍 Diagnostic complet';
});

document.addEventListener('DOMContentLoaded', function() {
    const generateBtn = document.getElementById('generate-cache-btn');
    const progressInfo = document.getElementById('progress-info');
    const progressBarFill = document.querySelector('.progress-bar-fill');
    const progressText = document.querySelector('.progress-text');
    const progressStatus = document.getElementById('progress-status');
    const resultsDiv = document.getElementById('generation-results');
    const btnIcon = generateBtn.querySelector('.btn-icon');
    const btnText = generateBtn.querySelector('.btn-text');
    const progressFill = generateBtn.querySelector('.progress-fill');
    
    let isProcessing = false;
    let totalCreated = { thumbnails: 0, resized: 0 };
    let totalErrors = [];
    
    generateBtn.addEventListener('click', function() {
        if (isProcessing) return;
        
        const options = {
            generate_thumbnails: document.getElementById('generate_thumbnails').checked,
            generate_resized: document.getElementById('generate_resized').checked,
            force_regenerate: document.getElementById('force_regenerate').checked
        };
        
        if (!options.generate_thumbnails && !options.generate_resized) {
            alert('Veuillez sélectionner au moins un type de cache à générer');
            return;
        }
        
        startGeneration(options);
    });
    
    function startGeneration(options) {
        isProcessing = true;
        totalCreated = { thumbnails: 0, resized: 0 };
        totalErrors = [];
        
        // État du bouton
        generateBtn.classList.add('processing');
        generateBtn.disabled = true;
        btnText.textContent = 'Génération en cours...';
        
        // Afficher la barre de progression
        progressInfo.style.display = 'block';
        resultsDiv.style.display = 'none';
        
        // Commencer le traitement
        processChunk(options, 0);
    }
    
    function processChunk(options, offset) {
        progressStatus.textContent = `Traitement des images (chunk ${Math.floor(offset/5) + 1})...`;
        
        const formData = new FormData();
        formData.append('ajax_action', 'process_cache_chunk');
        formData.append('offset', offset);
        
        if (options.generate_thumbnails) formData.append('generate_thumbnails', '1');
        if (options.generate_resized) formData.append('generate_resized', '1');
        if (options.force_regenerate) formData.append('force_regenerate', '1');
        
        fetch('admin_parameters.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Mettre à jour les statistiques
            totalCreated.thumbnails += data.thumbnails_created || 0;
            totalCreated.resized += data.resized_created || 0;
            totalErrors = totalErrors.concat(data.errors || []);
            
            // Mettre à jour la progression
            updateProgress(data.progress_percent, data.processed_photos, data.total_photos);
            
            if (data.completed) {
                completeGeneration();
            } else {
                // Continuer avec le chunk suivant
                setTimeout(() => {
                    processChunk(options, data.next_offset);
                }, 100);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showError('Erreur lors de la génération: ' + error.message);
            resetButton();
        });
    }
    
    function updateProgress(percent, processed, total) {
        // Mettre à jour la barre de progression
        progressBarFill.style.width = percent + '%';
        progressText.textContent = percent + '%';
        
        // Mettre à jour le remplissage du bouton
        progressFill.style.width = percent + '%';
        
        // Mettre à jour le statut
        progressStatus.textContent = `${processed}/${total} images traitées (${percent}%)`;
    }
    
    function completeGeneration() {
        isProcessing = false;
        
        // État final du bouton
        generateBtn.classList.remove('processing');
        generateBtn.classList.add('completed');
        btnText.textContent = 'Génération terminée ✓';
        
        // Afficher les résultats
        showResults();
        
        // Réinitialiser après 3 secondes
        setTimeout(resetButton, 3000);
    }
    
    function showResults() {
        const hasErrors = totalErrors.length > 0;
        
        resultsDiv.className = 'generation-results ' + (hasErrors ? 'error' : 'success');
        resultsDiv.innerHTML = `
            <h4>${hasErrors ? '⚠️' : '✅'} Génération terminée</h4>
            <div class="results-stats">
                <p><strong>Miniatures créées:</strong> ${totalCreated.thumbnails}</p>
                <p><strong>Images redimensionnées créées:</strong> ${totalCreated.resized}</p>
                ${hasErrors ? `<p><strong>Erreurs:</strong> ${totalErrors.length}</p>` : ''}
            </div>
            ${hasErrors ? `
                <details>
                    <summary>Voir les erreurs (${totalErrors.length})</summary>
                    <ul class="error-list">
                        ${totalErrors.map(error => `<li>${error}</li>`).join('')}
                    </ul>
                </details>
            ` : ''}
        `;
        resultsDiv.style.display = 'block';
    }
    
    function showError(message) {
        resultsDiv.className = 'generation-results error';
        resultsDiv.innerHTML = `<h4>❌ Erreur</h4><p>${message}</p>`;
        resultsDiv.style.display = 'block';
    }
    
    function resetButton() {
        generateBtn.classList.remove('processing', 'completed');
        generateBtn.disabled = false;
        btnText.textContent = 'Générer le cache';
        progressFill.style.width = '0%';
        progressInfo.style.display = 'none';
    }
});