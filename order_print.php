<!-- Section d'impression (cachée à l'écran) -->
<div id="print-content" class="print-only">
    <!-- Container principal qui sera rempli dynamiquement -->
    <div id="print-container">
        <!-- Page principale avec les deux exemplaires côte à côte -->
        <div class="print-single-page">
            <div class="print-duplicates">
                <!-- Exemplaire 1 - Adhérent -->
                <div class="print-copy">
                    <div class="copy-main-header">
                        <h1><?php echo(SITE_NAME); ?></h1>
                        <div class="copy-print-date">Imprimé le <span class="print-date-1"><?php echo($printDate); ?></span></div>
                    </div>
                    <div class="copy-header">
                        <h2>📋 RÉCAPITULATIF DE COMMANDE</h2>
                        <div class="copy-type">Exemplaire Adhérent</div>
                    </div>
                    
                    <div class="order-details">
                        <div class="detail-row">
                            <strong>Référence :</strong>
                            <span id="print-ref-1" class="highlight"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Date :</strong>
                            <span id="print-created-1"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Statut :</strong>
                            <span id="print-status-1"></span>
                        </div>
                    </div>
                    
                    <div class="customer-details">
                        <h3>👤 Informations client</h3>
                        <div class="detail-row">
                            <strong>Nom :</strong>
                            <span id="print-customer-1"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Email :</strong>
                            <span id="print-email-1"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Téléphone :</strong>
                            <span id="print-phone-1"></span>
                        </div>
                    </div>
                    
                    <!-- Section photos supprimée de la première page -->
                    <div class="photos-info">
                        <div class="total-section">
                            <strong>TOTAL : <span id="print-total-1"></span> photo(s)</strong>
                            <br><small>Détail des photos sur la page suivante</small>
                        </div>
                    </div>
                    
                    <div class="print-footer">
                        <p class="print-footer-user">Conservez précieusement cette référence pour tout suivi.</p>
                    </div>
                </div>
                
                <!-- Exemplaire 2 - Organisation -->
                <div class="print-copy">
                    <div class="copy-main-header">
                        <h1><?php echo(SITE_NAME) ?></h1>
                        <div class="copy-print-date">Imprimé le <span class="print-date-2"><?php echo($printDate); ?></span></div>
                    </div>
                    
                    <div class="copy-header">
                        <h2>📋 RÉCAPITULATIF DE COMMANDE</h2>
                        <div class="copy-type organization">Exemplaire Organisation</div>
                    </div>
                    
                    <div class="order-details">
                        <div class="detail-row">
                            <strong>Référence :</strong>
                            <span id="print-ref-2" class="highlight"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Date :</strong>
                            <span id="print-created-2"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Statut :</strong>
                            <span id="print-status-2"></span>
                        </div>
                    </div>
                    
                    <div class="customer-details">
                        <h3>👤 Informations client</h3>
                        <div class="detail-row">
                            <strong>Nom :</strong>
                            <span id="print-customer-2"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Email :</strong>
                            <span id="print-email-2"></span>
                        </div>
                        <div class="detail-row">
                            <strong>Téléphone :</strong>
                            <span id="print-phone-2"></span>
                        </div>
                    </div>
                    
                    <!-- Section photos supprimée de la première page -->
                    <div class="photos-info">
                        <div class="total-section">
                            <strong>TOTAL : <span id="print-total-2"></span> photo(s)</strong>
                            <br><small>Détail des photos sur la page suivante</small>
                        </div>
                    </div>
                    
                    <div class="print-footer">
                        <div class="status-section">
                            <h4>📝 Suivi de commande</h4>
                            <div class="status-boxes">
                                <div class="status-box">☐ Préparation</div>
                                <div class="status-box">☐ Prêt</div>
                                <div class="status-box">☐ Livré</div>
                            </div>
                            <div class="payment-section">
                                <div class="payment-line">
                                    <strong>Paiement :</strong> 
                                    <span class="checkbox">☐ Espèces</span>
                                    <span class="checkbox">☐ Chèque</span>
                                    <span class="checkbox">☐ CB</span>
                                </div>
                                <div class="payment-line">
                                    <strong>Montant :</strong> <span id="total-amount">0,00</span> €
                                    <strong style="margin-left: 2rem;">Le :</strong> ___________
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Containers cachés pour les données des tableaux -->
    <div style="display: none;">
        <!-- Données pour l'exemplaire adhérent -->
        <div id="hidden-items-1">
            <tbody id="print-items-1">
                <!-- Les données seront injectées ici par votre code PHP/JS existant -->
            </tbody>
        </div>
        
        <!-- Données pour l'exemplaire organisation -->
        <div id="hidden-items-2">
            <tbody id="print-items-2">
                <!-- Les données seront injectées ici par votre code PHP/JS existant -->
            </tbody>
        </div>
    </div>
</div>

<script>
let localPageNumber = 1;
// Fonction pour créer une page avec les deux tableaux côte à côte
function createPhotosPage(itemsAdherents, itemsOrganization, siteNameValue, printDate1, printDate2, orderData, pageNumber) {
    // Afficher "Page X/Y" si totalPages est disponible, sinon juste "Page X"
    const pageTitle = orderData.totalPages ?
        ` (Page ${pageNumber}/${orderData.totalPages})` :
        ` (Page ${pageNumber})`;

    // Déterminer l'ordre selon la page (pair/impair)
    const actualPageNumber = pageNumber;
    const isEvenPage = actualPageNumber % 2 === 0;
    
    let leftContent, rightContent;
    
    if (isEvenPage) {
        // Pages paires : Organisation à GAUCHE | Adhérent à DROITE
        leftContent = createTableContent('organization', itemsOrganization, siteNameValue, printDate2, orderData, pageTitle);
        rightContent = createTableContent('adherent', itemsAdherents, siteNameValue, printDate1, orderData, pageTitle);
    } else {
        // Pages impaires : Adhérent à GAUCHE | Organisation à DROITE  
        leftContent = createTableContent('adherent', itemsAdherents, siteNameValue, printDate1, orderData, pageTitle);
        rightContent = createTableContent('organization', itemsOrganization, siteNameValue, printDate2, orderData, pageTitle);
    }
    
    return `
        <div class="print-single-page">
            <div class="print-duplicates">
                ${leftContent}
                ${rightContent}
            </div>
        </div>
    `;
}

// Fonction pour créer le contenu d'un tableau
function createTableContent(type, items, siteNameValue, printDate, orderData, pageTitle) {
    const isOrganization = type === 'organization';
    const copyType = isOrganization ? 'Exemplaire Organisation' : 'Exemplaire Adhérent';
    const copyClass = isOrganization ? 'organization' : '';
    const extraColumn = isOrganization ? '<th></th>' : '';
    const totalKey = isOrganization ? 'totalPhotos2' : 'totalPhotos1';
    
    return `
        <div class="print-copy">
            <div class="copy-main-header">
                <h1>${siteNameValue}</h1>
                <div class="copy-print-date">Imprimé le ${printDate}</div>
            </div>
            
            <div class="copy-header">
                <h2>📋 RÉCAPITULATIF DE COMMANDE${pageTitle}</h2>
                <div class="copy-type ${copyClass}">${copyType}</div>
            </div>
            
            <div class="order-details">
                <div class="detail-row">
                    <strong>Référence :</strong>
                    <span class="highlight">${orderData.reference || ''}</span>
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
                        ${items}
                    </tbody>
                </table>
                <div class="total-section">
                    <strong>TOTAL : ${orderData[totalKey] || ''} photo(s)</strong>
                </div>
            </div>
        </div>
    `;
}
</script>