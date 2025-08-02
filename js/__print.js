/**
 * Système d'impression pour les bons de commande
 * @version 2.0 - Version corrigée sans pages vides
 */

let printOrderData = null;

/**
 * Fonction d'impression principale
 * @version 2.0
 */
function printOrder() {
    if (!printOrderData) {
        alert('Aucune donnée de commande à imprimer');
        return;
    }
    
    console.log('Démarrage de l\'impression pour:', printOrderData.reference);
    
    // Remplir les données d'impression
    fillPrintData(printOrderData);
    
    // Attendre que les données soient remplies puis imprimer
    setTimeout(() => {
        // Générer les pages supplémentaires si nécessaire
        generateAdditionalPages();
        
        // Supprimer les pages vides
        removeEmptyPrintPages();
        
        console.log('Impression lancée');
        window.print();
    }, 200);
}

/**
 * Préparer les données pour l'impression depuis l'admin
 * @param {string} reference - Référence de commande
 * @version 1.1
 */
function printOrderSlip(reference = null) {
    const orderReference = reference || currentOrderReference;
    
    if (!orderReference) {
        showNotification('Aucune commande sélectionnée', 'error');
        return;
    }
    
    const currentOrder = ordersData.find(o => o.reference === orderReference);
    if (!currentOrder) {
        showNotification('Commande introuvable', 'error');
        return;
    }

    const enrichedItems = currentOrder.photos.map(photo => ({
        ...photo,
        unit_price: getActivityPrice(photo.activity_key),
        type_info: getActivityTypeInfo(photo.activity_key),
        subtotal: getActivityPrice(photo.activity_key) * parseInt(photo.quantity)
    }));
    
    // Stocker les données pour l'impression
    printOrderData = {
        reference: currentOrder.reference,
        customer: {
            firstname: currentOrder.firstname,
            lastname: currentOrder.lastname,
            email: currentOrder.email,
            phone: currentOrder.phone
        },
        items: enrichedItems,
        created_at: currentOrder.created_at,
        retrieval_date: currentOrder.retrieval_date,
        total_photos: currentOrder.total_photos,
        total_amount: enrichedItems.reduce((sum, item) => sum + item.subtotal, 0),
        is_update: false
    };
    
    printOrder();
}

/**
 * Remplir les données d'impression dans le HTML
 * @param {Object} orderData - Données de la commande
 * @version 2.0
 */
function fillPrintData(orderData) {
    const now = new Date();
    const printDate = now.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    // Remplir les dates d'impression
    const dateElements = document.querySelectorAll('.print-date-1, .print-date-2');
    dateElements.forEach(el => el.textContent = printDate);
    
    // Informations communes
    const customerName = `${orderData.customer.firstname} ${orderData.customer.lastname}`;
    const createdDate = new Date(orderData.created_at).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    const statusText = orderData.is_update ? 'Mise à jour (remplace précédente)' : 'Nouvelle commande';
    const totalPhotos = Object.values(orderData.items).reduce((sum, item) => sum + item.quantity, 0);
    const totalAmount = GetTotalAmount(orderData);
    
    // Remplir les informations pour les deux exemplaires
    for (let i = 1; i <= 2; i++) {
        const elements = {
            ref: document.getElementById(`print-ref-${i}`),
            created: document.getElementById(`print-created-${i}`),
            status: document.getElementById(`print-status-${i}`),
            customer: document.getElementById(`print-customer-${i}`),
            email: document.getElementById(`print-email-${i}`),
            phone: document.getElementById(`print-phone-${i}`),
            total: document.getElementById(`print-total-${i}`)
        };
        
        // Vérifier que les éléments existent avant de les remplir
        if (elements.ref) elements.ref.textContent = orderData.reference;
        if (elements.created) elements.created.textContent = createdDate;
        if (elements.status) elements.status.textContent = statusText;
        if (elements.customer) elements.customer.textContent = customerName;
        if (elements.email) elements.email.textContent = orderData.customer.email;
        if (elements.phone) elements.phone.textContent = orderData.customer.phone;
        if (elements.total) elements.total.textContent = totalPhotos;
    }
    
    // Remplir le montant total
    const totalAmountElement = document.getElementById('total-amount');
    if (totalAmountElement) {
        totalAmountElement.textContent = totalAmount;
    }
    
    console.log('Données de base remplies pour', orderData.reference);
}

/**
 * Générer les pages supplémentaires avec le détail des photos
 * @version 2.1 - Sans pages vides
 */
function generateAdditionalPages() {
    if (!printOrderData || !printOrderData.items) {
        console.warn('Aucune donnée pour générer les pages supplémentaires');
        return;
    }
    
    const container = document.getElementById('print-container');
    if (!container) {
        console.error('Container d\'impression non trouvé');
        return;
    }
    
    // Nettoyer TOUTES les anciennes pages supplémentaires
    const existingPages = container.querySelectorAll('.print-single-page:not(:first-child)');
    existingPages.forEach(page => page.remove());
    
    const items = Object.values(printOrderData.items);
    
    // *** CORRECTION : Ne créer des pages QUE si il y a vraiment des photos ***
    if (items.length === 0) {
        console.log('Aucune photo à afficher, pas de page supplémentaire');
        return; // ← SORTIR ICI si pas de photos
    }
    
    const maxItemsPerPage = 12;
    const totalPages = Math.ceil(items.length / maxItemsPerPage);
    
    console.log(`Génération de ${totalPages} page(s) pour ${items.length} photo(s)`);
    
    // *** CORRECTION : Créer SEULEMENT le nombre de pages nécessaire ***
    for (let pageNum = 1; pageNum <= totalPages; pageNum++) {
        const startIndex = (pageNum - 1) * maxItemsPerPage;
        const endIndex = Math.min(startIndex + maxItemsPerPage, items.length);
        const pageItems = items.slice(startIndex, endIndex);
        
        // *** VÉRIFICATION : Ne créer la page QUE si elle a du contenu ***
        if (pageItems.length > 0) {
            const pageHTML = createDetailPage(pageItems, pageNum, totalPages);
            container.insertAdjacentHTML('beforeend', pageHTML);
        }
    }
}

/**
 * Créer une page de détail des photos
 * @param {Array} items - Items à afficher
 * @param {number} pageNumber - Numéro de la page
 * @param {number} totalPages - Nombre total de pages
 * @returns {string} HTML de la page
 * @version 2.0
 */
function createDetailPage(items, pageNumber, totalPages) {
    const siteName = document.querySelector('h1')?.textContent || 'GALA 2025';
    const printDate = new Date().toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    const orderReference = printOrderData.reference;
    const pageTitle = totalPages > 1 ? ` (Page ${pageNumber}/${totalPages})` : '';
    
    // Déterminer l'ordre des exemplaires (inversion 1 page sur 2)
    const isEvenPage = pageNumber % 2 === 0;
    
    // Générer les lignes du tableau pour chaque exemplaire
    const adherentRows = items.map(item => `
        <tr>
            <td>${item.activity_key}</td>
            <td>${getPhotoName(item)}</td>
            <td style="text-align: center;">${item.quantity}</td>
        </tr>
    `).join('');
    
    const organizationRows = items.map(item => `
        <tr>
            <td>${item.activity_key}</td>
            <td>${getPhotoName(item)}</td>
            <td style="text-align: center;">${item.quantity}</td>
            <td style="text-align: center;">☐</td>
        </tr>
    `).join('');
    
    // Créer les deux exemplaires
    const adherentCopy = createCopyHTML('Exemplaire Adhérent', '', adherentRows, siteName, printDate, orderReference, pageTitle);
    const organizationCopy = createCopyHTML('Exemplaire Organisation', 'organization', organizationRows, siteName, printDate, orderReference, pageTitle);
    
    // Inverser l'ordre sur les pages paires
    const leftCopy = isEvenPage ? organizationCopy : adherentCopy;
    const rightCopy = isEvenPage ? adherentCopy : organizationCopy;
    
    return `
        <div class="print-single-page">
            <div class="print-duplicates">
                ${leftCopy}
                ${rightCopy}
            </div>
        </div>
    `;
}

/**
 * Créer le HTML d'un exemplaire
 * @param {string} copyType - Type d'exemplaire
 * @param {string} copyClass - Classe CSS
 * @param {string} tableRows - Lignes du tableau
 * @param {string} siteName - Nom du site
 * @param {string} printDate - Date d'impression
 * @param {string} reference - Référence commande
 * @param {string} pageTitle - Titre de la page
 * @returns {string} HTML de l'exemplaire
 * @version 1.0
 */
function createCopyHTML(copyType, copyClass, tableRows, siteName, printDate, reference, pageTitle) {
    const extraColumn = copyClass === 'organization' ? '<th></th>' : '';
    const totalPhotos = Object.values(printOrderData.items).reduce((sum, item) => sum + item.quantity, 0);
    
    return `
        <div class="print-copy">
            <div class="copy-main-header">
                <h1>${siteName}</h1>
                <div class="copy-print-date">Imprimé le ${printDate}</div>
            </div>
            
            <div class="copy-header">
                <h2>📋 RÉCAPITULATIF DE COMMANDE${pageTitle}</h2>
                <div class="copy-type ${copyClass}">${copyType}</div>
            </div>
            
            <div class="order-details">
                <div class="detail-row">
                    <strong>Référence :</strong>
                    <span class="highlight">${reference}</span>
                </div>
            </div>
            
            <div class="items-details">
                <h3>📷 Photos commandées</h3>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Activité</th>
                            <th>Photo</th>
                            <th>Qté</th>
                            ${extraColumn}
                        </tr>
                    </thead>
                    <tbody>
                        ${tableRows}
                    </tbody>
                </table>
                <div class="total-section">
                    <strong>TOTAL : ${totalPhotos} photo(s)</strong>
                </div>
            </div>
        </div>
    `;
}

/**
 * Supprimer les pages vides ou sans contenu - VERSION AGRESSIVE
 * @version 2.1
 */
function removeEmptyPrintPages() {
    const allPages = document.querySelectorAll('.print-single-page');
    let removedCount = 0;
    
    allPages.forEach((page, index) => {
        // Critères stricts pour détecter une page vide
        const hasReference = page.querySelector('[id*="print-ref"]')?.textContent.trim();
        const hasTableRows = page.querySelectorAll('tbody tr').length;
        const hasSignificantContent = page.textContent.trim().length > 200;
        const hasPhotosInfo = page.querySelector('.photos-info');
        
        // Une page est vide si elle n'a RIEN de significatif
        const isEmpty = !hasReference && hasTableRows === 0 && !hasSignificantContent && !hasPhotosInfo;
        
        if (isEmpty) {
            console.log(`🗑️ Suppression page vide ${index + 1}`);
            page.remove();
            removedCount++;
        } else {
            console.log(`✅ Conservation page ${index + 1} (Ref: ${hasReference || 'N/A'}, Lignes: ${hasTableRows})`);
        }
    });
    
    const remainingPages = document.querySelectorAll('.print-single-page').length;
    console.log(`📊 ${removedCount} page(s) supprimée(s), ${remainingPages} page(s) conservée(s)`);
}

/**
 * Obtenir le nom d'une photo (version unifiée)
 * @param {Object|string} photo - Objet photo ou nom de fichier
 * @returns {string} Nom de la photo
 * @version 1.2
 */
function getPhotoName(photo) {
    if (!photo) {
        console.warn('getPhotoName: photo non définie');
        return 'photo_inconnue';
    }
    
    if (typeof photo === 'object') {
        return photo.photo_name || photo.name || photo.filename || 'photo_sans_nom';
    }
    
    if (typeof photo === 'string') {
        return photo;
    }
    
    console.warn('getPhotoName: format de photo non reconnu:', photo);
    return 'photo_erreur';
}

/**
 * Calculer le montant total de la commande
 * @param {Object} orderData - Données de la commande
 * @returns {number} Montant total
 * @version 1.0
 */
function GetTotalAmount(orderData) {
    if (!orderData || !orderData.items) return 0;
    
    return Object.values(orderData.items).reduce((total, item) => {
        return total + (item.subtotal || 0);
    }, 0);
}

/**
 * Fonction de debug pour compter les pages
 * @version 1.0
 */
function debugPrintPages() {
    const allPages = document.querySelectorAll('.print-single-page');
    let realPages = 0;
    let emptyPages = 0;

    console.group('Debug Pages d\'impression');
    
    allPages.forEach((page, index) => {
        const hasRealContent = page.textContent.trim().length > 100;
        const hasReference = page.querySelector('[id*="print-ref"]')?.textContent.trim();
        
        if (hasRealContent && hasReference) {
            realPages++;
            console.log(`Page ${index + 1}: VRAIE page (${hasReference})`);
        } else {
            emptyPages++;
            console.log(`Page ${index + 1}: VIDE ou incomplète`);
        }
    });
    
    console.log(`RÉSULTAT: ${realPages} vraies pages, ${emptyPages} pages vides`);
    console.groupEnd();
    
    return { realPages, emptyPages, totalPages: allPages.length };
}

// Event listener pour debug (à supprimer en production)
if (typeof window !== 'undefined') {
    window.debugPrintPages = debugPrintPages;
}