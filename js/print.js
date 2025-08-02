let printOrderData = null;
let call = 1;

// Fonction d'impression
function printOrder() {
    if (!printOrderData) {
        alert('Aucune donnée de commande à imprimer');
        return;
    }
    
    console.log('Données d\'impression :', printOrderData);
    // Remplir les données d'impression
    fillPrintData(printOrderData);

    // ATTENDRE que les pages soient créées avant d'imprimer
    setTimeout(() => {
        // Appeler la fonction d'impression du navigateur
        window.print();
    }, 500)
}


/**
 * Imprimer le bon de commande
 */
function printOrderSlip(reference = null) {
    // Utiliser la référence passée en paramètre ou celle stockée globalement
    const orderReference = reference || currentOrderReference;
    
    if (!orderReference) {
        showNotification('Aucune commande sélectionnée', 'error');
        return;
    }
    
    // Trouver les détails de la commande
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
        items: enrichedItems, // ✅ Utiliser les données enrichies
        created_at: currentOrder.created_at,
        retrieval_date: currentOrder.retrieval_date,
        total_photos: currentOrder.total_photos,
        total_amount: enrichedItems.reduce((sum, item) => sum + item.subtotal, 0), // ✅ Recalculer le total
        is_update: false
    };
    
    // Appeler la fonction d'impression
    printOrder();
}

// Remplir les données pour l'impression
function fillPrintData(orderData) {

    const now = new Date();
    const printDate = now.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    // Remplir les deux dates séparément
    document.querySelector('.print-date-1').textContent = printDate;
    document.querySelector('.print-date-2').textContent = printDate;
    
    // Informations communes aux deux exemplaires
    const customerName = orderData.customer.firstname + ' ' + orderData.customer.lastname;
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
    
    // Remplir les deux exemplaires
    for (let i = 1; i <= 2; i++) {
        document.getElementById(`print-ref-${i}`).textContent = orderData.reference;
        document.getElementById(`print-created-${i}`).textContent = createdDate;
        document.getElementById(`print-status-${i}`).textContent = statusText;
        document.getElementById(`print-customer-${i}`).textContent = customerName;
        document.getElementById(`print-email-${i}`).textContent = orderData.customer.email;
        document.getElementById(`print-phone-${i}`).textContent = orderData.customer.phone;
        document.getElementById(`print-total-${i}`).textContent = totalPhotos;
        document.getElementById(`total-amount`).textContent = totalAmount;

        // Vérifier si l'élément existe, sinon le créer
        let itemsTable = document.getElementById(`print-items-${i}`);
        if (!itemsTable) {
            // Créer l'élément s'il n'existe pas
            itemsTable = document.createElement('tbody');
            itemsTable.id = `print-items-${i}`;
            
            // L'ajouter au container caché
            const hiddenContainer = document.getElementById(`hidden-items-${i}`) || 
                                  document.querySelector('.print-only');
            if (hiddenContainer) {
                hiddenContainer.appendChild(itemsTable);
            }
        }
    
        itemsTable.innerHTML = '';

        if(i === 2) {
            Object.values(orderData.items).forEach(item => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${item.activity_key}</td>
                    <td>${getPhotoName(item)}</td>
                    <td style="text-align: center;">${item.quantity}</td>
                    <td style="text-align: center;">☐</td>
                `;
                itemsTable.appendChild(row);
            });
        } else {
            Object.values(orderData.items).forEach(item => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${item.activity_key}</td>
                    <td>${getPhotoName(item)}</td>
                    <td style="text-align: center;">${item.quantity}</td>
                `;
                itemsTable.appendChild(row);
            });
        }

        // Nettoyer les anciennes pages
        const container = document.getElementById('print-container');
        const continuationPages = container.querySelectorAll('.print-single-page:not(:first-child)');
        //continuationPages.forEach(page => page.remove());
        
    }
    
    // IMPORTANT : Appeler après avoir rempli les données
    setTimeout(() => {
        if (typeof handlePrintOverflow === 'function') {
            handlePrintOverflow();
        } else {
            console.error('handlePrintOverflow non trouvée');
        }
    }, 500);
}

// Fonction principale pour gérer l'impression avec pages séparées
function handlePrintOverflow() {
    let pageNumber = 1;
    const maxItemsPerPage = 12;   // Nombre max d'éléments par page pour les pages photos
    const container = document.getElementById('print-container');

    
    // Récupérer les données communes
    const siteNameValue = document.querySelector('h1').textContent || '';
    const printDate1 = document.querySelector('.print-date-1').textContent || new Date().toLocaleDateString('fr-FR');
    const printDate2 = document.querySelector('.print-date-2').textContent || new Date().toLocaleDateString('fr-FR');
    
    const orderData = {
        reference: document.getElementById('print-ref-1').textContent || '',
        totalPhotos1: document.getElementById('print-total-1').textContent || '0',
        totalPhotos2: document.getElementById('print-total-2').textContent || '0'
    };
    
    // Vérifier et récupérer les données des tableaux
    // D'abord essayer depuis les containers cachés, sinon créer des données de test
    let items1Container = getItemsFromDOM('print-items-1');
    let items2Container = getItemsFromDOM('print-items-2');
    
    // Si pas de données, créer des exemples pour test
    if (items1Container.length === 0) {
        console.log('Aucune donnée trouvée pour print-items-1, création de données de test');
        items1Container = createTestData();
    }
    
    if (items2Container.length === 0) {
        console.log('Aucune donnée trouvée pour print-items-2, création de données de test');
        items2Container = createTestData(true); // true pour ajouter colonne supplémentaire
    }
    
    // Créer l'objet orderData proprement
    const orderDataForPages = {
        reference: orderData.reference,
        totalPhotos1: orderData.totalPhotos1,
        totalPhotos2: orderData.totalPhotos2,
        totalPhotos: orderData.totalPhotos1
    };


    createPagesForCopy(
        items1Container, 
        items2Container, 
        maxItemsPerPage, 
        container, 
        siteNameValue, 
        printDate1, 
        orderDataForPages
    );
  
}

// Fonction pour créer des données de test (à supprimer quand vos vraies données fonctionnent)
function createTestData(hasExtraColumn = false) {
    const testItems = [];
    const extraColumn = hasExtraColumn ? '<td>☐</td>' : '';
    
    for (let i = 1; i <= 25; i++) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>Activité ${i}</td>
            <td>Photo_${String(i).padStart(3, '0')}.jpg</td>
            <td>1</td>
            ${extraColumn}
        `;
        testItems.push(tr);
    }
    
    return testItems;
}

// Fonction pour récupérer les éléments du DOM depuis les containers cachés
function getItemsFromDOM(containerId) {
    const container = document.getElementById(containerId);
    if (!container) {
        console.warn(`Container ${containerId} non trouvé`);
        return [];
    }
    
    // Retourner les éléments du tbody
    const items = Array.from(container.children);
    return items;
}

function createPagesForCopy(ItemsAdherents, ItemsOrganization, maxItemsPerPage, container, siteNameValue, printDate, orderData) {
    // Créer au minimum une page, même si elle est vide

console.log('Création des pages pour la copie');
console.log(pageNumber);

    if (ItemsAdherents.length === 0) {
        const photosPage = createPhotosPage(
            type,
            '', // Pas d'éléments
            siteNameValue,
            printDate,
            orderData,
            1
        );
        container.insertAdjacentHTML('beforeend', photosPage);
        return;
    }
    
    let startIndex = 0;
    
    while (startIndex < ItemsAdherents.length) {
        const endIndex = Math.min(startIndex + maxItemsPerPage, ItemsAdherents.length);
        const pageItemsAdherents = ItemsAdherents.slice(startIndex, endIndex);
        const pageItemsOrganization = ItemsOrganization.slice(startIndex, endIndex);
        const itemsHTMLAdherents = pageItemsAdherents.map(item => item.outerHTML || item).join('');
        const itemsHTMLOrganization = pageItemsOrganization.map(item => item.outerHTML || item).join('');
        
        // CORRECTION : Déterminer si on inverse selon le numéro de page
        // Page 1 = pas d'inversion, Page 2 = inversion, Page 3 = pas d'inversion, etc.
        const shouldInvert = pageNumber % 2 === 0;

        console.log(`Inversion ${shouldInvert} numéro de page ${pageNumber}`);
        
        let leftHTML, rightHTML;
        if (shouldInvert) {
            // Pages paires : Organisation à gauche, Adhérent à droite
            leftHTML = itemsHTMLOrganization;
            rightHTML = itemsHTMLAdherents;
        } else {
            // Pages impaires : Adhérent à gauche, Organisation à droite
            leftHTML = itemsHTMLAdherents;
            rightHTML = itemsHTMLOrganization;
        }
        
        const photosPage = createPhotosPage(
            leftHTML,
            rightHTML,
            siteNameValue,
            printDate,
            printDate,
            orderData,
            pageNumber,
            shouldInvert
        );
        
        if(pageNumber>1)
            container.insertAdjacentHTML('beforeend', photosPage);
        
        startIndex = endIndex;

        pageNumber++;
    }
}

// Appeler la fonction avant l'impression
window.addEventListener('beforeprint', function() {
    // Nettoyer d'abord les pages de continuation existantes
    const container = document.getElementById('print-container');
    console.log('Nettoyage des pages de continuation avant impression');
    console.log('Container:', container);

    // Supprimer toutes les pages de continuation sauf la première  
    //const continuationPages = container.querySelectorAll('.print-single-page:not(:first-child)');
    //continuationPages.forEach(page => page.remove());
    
    
    // Puis gérer le débordement
    handlePrintOverflow();

    
});

/**
 * Supprimer les pages vides avant impression
 * @version 1.0
 */
function removeEmptyPrintPages() {
    const allPages = document.querySelectorAll('.print-single-page');
    
    allPages.forEach(page => {
        // Vérifier si la page a du contenu réel
        const refElement = page.querySelector('[id*="print-ref"]');
        const hasContent = refElement && refElement.textContent.trim() !== '';
        
        if (!hasContent) {
            console.log('Suppression d\'une page vide détectée');
            page.remove();
        }
    });
}


function testPage(){
// Compter pages réelles vs vides
let realPages = 0;
let emptyPages = 0;

document.querySelectorAll('.print-single-page').forEach((page, index) => {
    const hasRealContent = page.textContent.trim().length > 100;
    if (hasRealContent) {
        realPages++;
        console.log(`Page ${index + 1}: VRAIE page`);
    } else {
        emptyPages++;
        console.log(`Page ${index + 1}: VIDE ou presque vide`);
        page.style.border = '5px solid red'; // Marquer visuellement
    }
});

console.log(`RÉSULTAT: ${realPages} vraies pages, ${emptyPages} pages vides`);

}