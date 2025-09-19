// Script de test pour déboguer le problème de rafraîchissement après paiement
console.log('=== TEST DEBUG PAYMENT REFRESH ===');

// Test 1: Vérifier la disponibilité des fonctions globales
console.log('\n1. Vérification des fonctions globales:');
console.log('typeof window.switchTab:', typeof window.switchTab);
console.log('typeof processPayment:', typeof processPayment);
console.log('window.currentStatus:', window.currentStatus);

// Test 2: Simuler un appel de processPayment
console.log('\n2. Test de la logique après paiement:');
function simulatePaymentSuccess() {
    console.log('🎉 Simulation: Paiement traité avec succès');

    // Copie de la logique de rafraîchissement
    console.log('⏱️ Démarrage du timeout de 500ms...');
    setTimeout(() => {
        console.log('🔄 Timeout écoulé, vérification de switchTab...');
        console.log('typeof window.switchTab:', typeof window.switchTab);
        console.log('window.currentStatus:', window.currentStatus);

        if (typeof window.switchTab === 'function') {
            console.log('✅ switchTab trouvée, appel avec status:', window.currentStatus || 'unpaid');
            window.switchTab(window.currentStatus || 'unpaid');
        } else {
            console.log('❌ switchTab non trouvée, rechargement de page nécessaire');
        }
    }, 500);
}

// Test 3: Vérifier l'accessibilité du DOM
console.log('\n3. Vérification du DOM:');
console.log('paymentForm:', document.getElementById('paymentForm'));
console.log('paymentModal:', document.getElementById('paymentModal'));

// Lancer la simulation
console.log('\n4. Lancement de la simulation...');
simulatePaymentSuccess();

// Test 4: Vérifier les onglets
console.log('\n5. Vérification des onglets:');
const tabButtons = document.querySelectorAll('.tab-button');
console.log('Nombre d\'onglets trouvés:', tabButtons.length);
tabButtons.forEach((tab, index) => {
    console.log(`  Onglet ${index}: status="${tab.dataset.status}", actif="${tab.classList.contains('active')}"`);
});