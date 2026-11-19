/**
 * Silkroad Online - Battle Pass JavaScript API Client
 * IE7 Compatible Version - Works with existing jquery.scroll.js and ingame_shell.js
 */

// ============================================================
// CONFIGURATION
// ============================================================
var BP_CONFIG = {
    API_BASE: '/in-game/battlepass',
    ITEM_ICON_BASE: '/images/sro/',
    DEFAULT_ICON: '/images/sro/icon_default.png',
    SILK_ICON: '/ingame/battle-pass/images/points.png',
    PREMIUM_SILK_ICON: '/ingame/battle-pass/images/silk.png',
    REFRESH_INTERVAL: 30000,
    PREMIUM_ENABLED: false,  // Set to false to disable premium pass features
    PREMIUM_PRICE: 500,      // Overridden by server config on first load
    POINTS_PER_PURCHASE: 1,  // Overridden by server config on first load
    SILK_PER_POINT: 50       // Overridden by server config on first load
};

// ============================================================
// IE7 HELPERS
// ============================================================
// Escape values before inserting them into innerHTML. Item, guild and
// character names originate from the game database, so never trust them as
// markup.
function bpEscapeHtml(value) {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// IE7-safe getElementsByClassName (IE7 only has getElementsByTagName).
function bpGetElementsByClassName(className, rootNode) {
    var root = rootNode || document;
    var results = [];
    var all = root.getElementsByTagName('*');
    var i, el, classes;

    for (i = 0; i < all.length; i++) {
        el = all[i];
        classes = ' ' + (el.className || '') + ' ';
        if (classes.indexOf(' ' + className + ' ') !== -1) {
            results.push(el);
        }
    }

    return results;
}

// ============================================================
// URL PARAMETER PARSER
// ============================================================
var URLParams = {
    get: function(key) {
        var query = window.location.search.substring(1);
        var vars = query.split('&');
        for (var i = 0; i < vars.length; i++) {
            var pair = vars[i].split('=');
            if (pair.length >= 2 && decodeURIComponent(pair[0]) === key) {
                return decodeURIComponent(pair[1]);
            }
        }
        return '';
    },
    getAll: function() {
        return {
            webtoken: this.get('webtoken'),
            charname: this.get('charname'),
            username: this.get('username'),
            uniqueid: this.get('uniqueid'),
            jid: this.get('jid'),
            key: this.get('key')
        };
    }
};

// ============================================================
// API CLIENT
// ============================================================
var BattlePassAPI = {
    buildQuery: function() {
        var params = URLParams.getAll();
        var query = [];
        if (params.charname) query.push('charname=' + encodeURIComponent(params.charname));
        if (params.webtoken) query.push('webtoken=' + encodeURIComponent(params.webtoken));
        if (params.username) query.push('username=' + encodeURIComponent(params.username));
        if (params.uniqueid) query.push('uniqueid=' + encodeURIComponent(params.uniqueid));
        if (params.jid) query.push('jid=' + encodeURIComponent(params.jid));
        if (params.key) query.push('key=' + encodeURIComponent(params.key));
        // Cache-busting for IE7 which aggressively caches AJAX GET requests
        query.push('_t=' + new Date().getTime());
        return query.join('&');
    },

    buildUrl: function(path) {
        return BP_CONFIG.API_BASE + '/' + path + '?' + this.buildQuery();
    },

    request: function(method, path, body, callback) {
        var xhr = new XMLHttpRequest();
        var url = this.buildUrl(path);

        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                var data;
                try {
                    if (window.JSON && window.JSON.parse) {
                        data = window.JSON.parse(xhr.responseText);
                    } else {
                        data = eval('(' + xhr.responseText + ')');
                    }
                } catch (e) {
                    data = { success: false, error: 'Server returned an invalid response (HTTP ' + xhr.status + '). Check storage/database tables and migrations.' };
                }
                if (xhr.status === 200) {
                    callback(data);
                } else {
                    callback(data || { success: false, error: 'Network error: ' + xhr.status });
                }
            }
        };

        xhr.open(method, url, true);

        if (method === 'POST') {
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        }

        xhr.send(body || null);
    },

    getData: function(callback) {
        this.request('GET', 'tiers', null, callback);
    },

    claimItem: function(tierID, type, callback) {
        this.request('POST', 'claim', 'tier_id=' + encodeURIComponent(tierID) + '&type=' + encodeURIComponent(type || 'free'), callback);
    },

    purchasePremium: function(callback) {
        this.request('POST', 'purchase-premium', '', callback);
    },

    purchasePoints: function(qty, callback) {
        this.request('POST', 'purchase-points', 'qty=' + encodeURIComponent(qty), callback);
    },

    getSilk: function(callback) {
        this.request('GET', 'silk', null, callback);
    }
};

// ============================================================
// UI MANAGER
// ============================================================
var BattlePassUI = {
    currentData: null,
    currentTab: 'free',
    currentPage: 1,
    itemsPerPage: 6,

    init: function() {
        var self = this;
        var params = URLParams.getAll();
        // Support multiple guard systems: charname (none), webtoken (maxiguard), TokenID (vplus), jid+key (isro)
        /*
        var hasCredentials = params.charname || (params.jid && params.key) || params.webtoken;
        if (!hasCredentials) {
            this.showError('Character name or guard credentials (jid+key / webtoken) are required in URL parameters');
            return;
        }
         */
        this.buildTabs();
        this.refresh();
        this.setupEventListeners();
        setInterval(function() { self.refresh(); }, BP_CONFIG.REFRESH_INTERVAL);
    },

    refresh: function() {
        var self = this;
        BattlePassAPI.getData(function(data) {
            if (!data.success) {
                self.showError(data.error || data.message || 'Failed to load battle pass data');
                return;
            }
            self.currentData = data;
            self.render(data);
        });
    },

    render: function(data) {
        // Sync PREMIUM_ENABLED with server config if provided
        if (data && typeof data.premiumEnabled !== 'undefined') {
            BP_CONFIG.PREMIUM_ENABLED = data.premiumEnabled;
        }
        if (data && typeof data.premiumPrice !== 'undefined') {
            BP_CONFIG.PREMIUM_PRICE = data.premiumPrice;
        }
        if (data && typeof data.pointsPerPurchase !== 'undefined') {
            BP_CONFIG.POINTS_PER_PURCHASE = data.pointsPerPurchase;
        }
        if (data && typeof data.silkPerPoint !== 'undefined') {
            BP_CONFIG.SILK_PER_POINT = data.silkPerPoint;
        }
        this.renderHeader(data);
        this.renderProgress(data);
        this.renderTopTier(data);
        this.renderPurchasePanel(data);
        this.renderTiers(data);
        this.updatePremiumVisibility();
    },

    renderHeader: function(data) {
        var freeTab = document.getElementById('gnb-free');
        var premTab = document.getElementById('gnb-prem');

        if (!freeTab || !premTab) return;

        if (this.currentTab === 'premium') {
            premTab.className = 'current';
            freeTab.className = '';
        } else {
            freeTab.className = 'current';
            premTab.className = '';
        }
    },

    renderProgress: function(data) {
        var stats = data.stats;
        var progressBar = document.getElementById('progress-bar');
        var progressText = document.getElementById('progress-text');
        if (progressBar) {
            progressBar.style.width = stats.progressPercent + '%';
        }
        if (progressText) {
            progressText.innerHTML = stats.currentLevel + '/' + stats.maxLevel;
        }
    },

    renderTopTier: function(data) {
        var tiers = data.tiers;
        var stats = data.stats;
        var record = data.record;
        var maxTier = tiers[tiers.length - 1];
        var isMaxLevel = stats.currentLevel >= stats.maxLevel;
        var isPremiumTab = this.currentTab === 'premium';

        this.ensureTopTierMount();
        var topTierContainer = document.getElementById('top-tier-list');
        if (!topTierContainer) return;

        var item = isPremiumTab ? maxTier.vipItem : maxTier.freeItem;
        var itemType = isPremiumTab ? 'VIP' : 'Free';
        var liClass = isPremiumTab ? 'prem' : '';
        var iconUrl = item.icon ? BP_CONFIG.ITEM_ICON_BASE + item.icon + '.png' : BP_CONFIG.DEFAULT_ICON;

        var buttonHTML = '';
        if (isPremiumTab && !record.isPremium) {
            buttonHTML = '<span class="btn-ga btn-ga-cancel"><a href="#" onclick="BattlePassUI.showPremiumPopup(); return false;">Premium Only</a></span>';
        } else if (!isMaxLevel) {
            buttonHTML = '<span class="btn-ga btn-ga-cancel"><a href="#">Not Enough Lv</a></span>';
        } else {
            var isClaimed = isPremiumTab ? maxTier.vipClaimed : maxTier.freeClaimed;
            if (isClaimed) {
                buttonHTML = '<span class="btn-ga btn-ga-cancel"><a href="#">Claimed</a></span>';
            } else {
                var claimType = isPremiumTab ? 'vip' : 'free';
                buttonHTML = '<span class="btn-ga"><a href="#" onclick="BattlePassUI.handleClaim(' + maxTier.id + ', \'' + claimType + '\'); return false;">Claim!</a></span>';
            }
        }

        topTierContainer.innerHTML =
            '<li class="' + liClass + '" dir="ltr">' +
            '<div class="intro">' +
            '<a class="pic"><img src="' + bpEscapeHtml(iconUrl) + '" alt="' + bpEscapeHtml(item.name) + '" onerror="this.src=\'' + BP_CONFIG.DEFAULT_ICON + '\'" /></a>' +
            '<span class="name">' + bpEscapeHtml(item.name) + '</span>' +
            '<div class="price">' +
            '<span class="type"><img src="' + BP_CONFIG.SILK_ICON + '" alt="Silk" /></span>' +
            '<strong class="val">Lv. ' + stats.maxLevel + '</strong>' +
            '</div>' +
            '<div class="price">' +
            '<span class="type"><img src="' + BP_CONFIG.SILK_ICON + '" alt="Silk" /></span>' +
            '<strong class="val">' + itemType + ' Reward</strong>' +
            '</div>' +
            '</div>' +
            '<div class="action">' +
            '<span class="setter">' + buttonHTML + '</span>' +
            '</div>' +
            '</li>';

        this.bindTooltips(topTierContainer, [item]);
    },

    renderPurchasePanel: function(data) {
        var record = data.record;
        var stats = data.stats;

        this.ensurePurchaseMount();
        var purchaseContainer = document.getElementById('purchase-list');
        if (!purchaseContainer) return;

        var passType = record.isPremium ? 'Premium' : 'Free';
        var passBadge = record.isPremium ? '/ingame/battle-pass/images/badge-premium.png' : '/ingame/battle-pass/images/badge-free.png';
        var premClass = record.isPremium ? 'prem' : '';

        var premiumButtonHTML = '';
        if (BP_CONFIG.PREMIUM_ENABLED) {
            if (!record.isPremium) {
                premiumButtonHTML =
                    '<div class="action">' +
                    '<span class="setter">' +
                    '<span class="btn-ga">' +
                    '<a href="#" onclick="BattlePassUI.handlePurchasePremium(); return false;">Purchase Premium</a>' +
                    '</span>' +
                    '</span>' +
                    '</div>';
            } else {
                premiumButtonHTML =
                    '<div class="action">' +
                    '<span class="setter">' +
                    '<span class="btn-ga btn-ga-cancel">' +
                    '<a href="#">Premium Active</a>' +
                    '</span>' +
                    '</span>' +
                    '</div>';
            }
        }

        // Build HTML that preserves the structure qtyAdj() expects:
        // <td class="qty"><div class="val"><input type="text".../></div></td>
        // After qtyAdj runs it adds: <span class="ctrl"><span class="add"></span><span class="minus"></span></span>
        purchaseContainer.innerHTML =
            '<li class="' + premClass + '" dir="ltr">' +
            '<div class="intro purchase-pass-details">' +
            '<img src="' + passBadge + '" alt="" />' +
            '<span class="name" style="font-weight: bold; margin-top: 10px">' + bpEscapeHtml(data.character.charname) + '</span>' +
            '<span class="name">' + passType + ' Battle Pass</span>' +
            '<span class="name" style="color: #ffcc00;">Points: ' + stats.currentPoints + '</span>' +
            '</div>' +
            '<div class="intro">' +
            '<div class="buyitem">' +
            '<div class="details">' +
            '<p>Purchase</p>' +
            '<span class="qty-inline">' +
            '<input type="text" name="qty" id="qty" size="3" maxlength="2" value="1" />' +
            '<span class="qty-arrows">' +
            '<span class="qty-plus" onclick="BattlePassUI.adjustQty(1)"></span>' +
            '<span class="qty-minus" onclick="BattlePassUI.adjustQty(-1)"></span>' +
            '</span>' +
            '</span>' +
            '<p>Points</p>' +
            '</div>' +
            '</div>' +
            '<div class="price">' +
            '<span class="type"><img src="' + BP_CONFIG.SILK_ICON + '" alt="Silk" /></span>' +
            '<strong class="val">1 Point = ' + BP_CONFIG.SILK_PER_POINT + ' Silk</strong>' +
            '</div>' +
            '<div class="price">' +
            '<span class="type"><img src="' + BP_CONFIG.PREMIUM_SILK_ICON + '" alt="Silk" /></span>' +
            '<strong class="val" id="total-silk-cost">Total Silk Cost: ' + (BP_CONFIG.SILK_PER_POINT * BP_CONFIG.POINTS_PER_PURCHASE) + '</strong>' +
            '</div>' +
            (BP_CONFIG.PREMIUM_ENABLED ?
                '<div class="price">' +
                '<span class="type"><img src="' + BP_CONFIG.PREMIUM_SILK_ICON + '" alt="Silk" /></span>' +
                '<strong class="val">Premium Pass: ' + BP_CONFIG.PREMIUM_PRICE + '</strong>' +
                '</div>' : '') +
            '</div>' +
            '<div class="action">' +
            '<span class="setter">' +
            '<span class="btn-ga">' +
            '<a href="#" onclick="BattlePassUI.handlePurchasePoints(); return false;">Purchase Points</a>' +
            '</span>' +
            '</span>' +
            '</div>' +
            premiumButtonHTML +
            '</li>';

        // Setup cost update on input change
        var qtyInput = document.getElementById('qty');
        if (qtyInput) {
            var self = this;
            qtyInput.onchange = function() { self.updateTotalCost(); };
            qtyInput.onkeyup = function() { self.updateTotalCost(); };
        }
    },

    // Fallback quantity handler if qtyAdj plugin is not available
    setupQtyFallback: function() {
        var qtyInput = document.getElementById('qty');
        if (!qtyInput) return;

        var parent = qtyInput.parentNode;
        while (parent && parent.className.indexOf('qty') === -1) {
            parent = parent.parentNode;
            if (!parent || parent === document.body) break;
        }
        if (!parent || parent === document.body) return;

        // Check if arrows already exist
        var existingCtrl = parent.getElementsByTagName('span');
        var hasCtrl = false;
        for (var i = 0; i < existingCtrl.length; i++) {
            if (existingCtrl[i].className === 'ctrl') {
                hasCtrl = true;
                break;
            }
        }
        if (hasCtrl) return;

        var self = this;
        var ctrlSpan = document.createElement('span');
        ctrlSpan.className = 'ctrl';
        ctrlSpan.innerHTML = '<span class="add"></span><span class="minus"></span>';

        var valDiv = parent.getElementsByTagName('div')[0];
        if (valDiv) {
            valDiv.appendChild(ctrlSpan);
        }

        var addBtn = null, minusBtn = null;
        var spans = ctrlSpan.getElementsByTagName('span');
        for (var j = 0; j < spans.length; j++) {
            if (spans[j].className === 'add') addBtn = spans[j];
            if (spans[j].className === 'minus') minusBtn = spans[j];
        }

        if (addBtn) {
            addBtn.onclick = function() {
                var currentVal = parseInt(qtyInput.value) || 1;
                if (currentVal < 99) {
                    qtyInput.value = currentVal + 1;
                    self.updateTotalCost();
                }
            };
        }
        if (minusBtn) {
            minusBtn.onclick = function() {
                var currentVal = parseInt(qtyInput.value) || 1;
                if (currentVal > 1) {
                    qtyInput.value = currentVal - 1;
                    self.updateTotalCost();
                }
            };
        }
    },

    updateTotalCost: function() {
        var qtyInput = document.getElementById('qty');
        if (!qtyInput) return;
        var qty = parseInt(qtyInput.value) || 1;
        if (qty < 1) qty = 1;
        if (qty > 99) qty = 99;
        qtyInput.value = qty;
        var totalCost = qty * BP_CONFIG.SILK_PER_POINT * BP_CONFIG.POINTS_PER_PURCHASE;
        var totalElement = document.getElementById('total-silk-cost');
        if (totalElement) {
            totalElement.innerHTML = 'Total Silk Cost: ' + totalCost;
        }
    },

    adjustQty: function(delta) {
        var qtyInput = document.getElementById('qty');
        if (!qtyInput) return;
        var currentVal = parseInt(qtyInput.value) || 1;
        var newVal = currentVal + delta;
        if (newVal < 1) newVal = 1;
        if (newVal > 99) newVal = 99;
        qtyInput.value = newVal;
        this.updateTotalCost();
    },

    renderTiers: function(data) {
        var tiers = data.tiers;
        var record = data.record;
        var stats = data.stats;
        var isPremiumTab = this.currentTab === 'premium';

        var listContainer = document.getElementById('tier-list');
        if (!listContainer) return;

        var totalPages = Math.max(1, Math.ceil(tiers.length / this.itemsPerPage));
        if (this.currentPage > totalPages) this.currentPage = totalPages;
        if (this.currentPage < 1) this.currentPage = 1;

        var startIndex = (this.currentPage - 1) * this.itemsPerPage;
        var pageTiers = tiers.slice(startIndex, startIndex + this.itemsPerPage);

        var html = '';

        for (var i = 0; i < pageTiers.length; i++) {
            var tier = pageTiers[i];
            var isUnlocked = stats.currentLevel >= tier.level;
            var isBest = tier.level === 5 || tier.level === 10;
            var item = isPremiumTab ? tier.vipItem : tier.freeItem;
            var liClass = isPremiumTab ? 'prem' : '';
            var iconUrl = item.icon ? BP_CONFIG.ITEM_ICON_BASE + item.icon + '.png' : BP_CONFIG.DEFAULT_ICON;
            var bestClass = isBest ? 'tag-best' : '';

            var buttonHTML = '';
            if (isPremiumTab && !record.isPremium) {
                buttonHTML = '<span class="btn-ga btn-ga-cancel"><button type="button" onclick="BattlePassUI.showPremiumPopup()">Premium Only</button></span>';
            } else if (!isUnlocked) {
                buttonHTML = '<span class="btn-ga btn-ga-cancel"><button type="button">Not Enough Lv</button></span>';
            } else {
                var isClaimed = isPremiumTab ? tier.vipClaimed : tier.freeClaimed;
                if (isClaimed) {
                    buttonHTML = '<span class="btn-ga btn-ga-cancel"><button type="button">Claimed</button></span>';
                } else {
                    var claimType = isPremiumTab ? 'vip' : 'free';
                    buttonHTML = '<span class="btn-ga"><button type="button" onclick="BattlePassUI.handleClaim(' + tier.id + ', \'' + claimType + '\')">Claim!</button></span>';
                }
            }

            html +=
                '<li class="' + liClass + '">' +
                '<div class="intro">' +
                '<a rel="#item-' + tier.id + '" class="pic"><img src="' + bpEscapeHtml(iconUrl) + '" alt="' + bpEscapeHtml(item.name) + '" onerror="this.src=\'' + BP_CONFIG.DEFAULT_ICON + '\'" /></a>' +
                '<span class="name">' + bpEscapeHtml(item.name) + '</span>' +
                '<span class="tag ' + bestClass + '">Lv ' + tier.level + '</span>' +
                '</div>' +
                '<div class="price">' +
                '<font color="#cc1a3">Quantity: ' + item.quantity + '</font>' +
                '</div>' +
                '<div class="action">' +
                '<span class="setter">' + buttonHTML + '</span>' +
                '</div>' +
                '</li>';
        }

        listContainer.innerHTML = html;

        var tierItems = [];
        for (var i = 0; i < pageTiers.length; i++) {
            tierItems.push(isPremiumTab ? pageTiers[i].vipItem : pageTiers[i].freeItem);
        }
        this.bindTooltips(listContainer, tierItems);
        this.renderPagination(totalPages);
    },

    // ============================================================
    // ITEM TOOLTIPS
    // ============================================================

    ensureTooltipElem: function() {
        var el = document.getElementById('bp-tooltip');
        if (!el) {
            el = document.createElement('div');
            el.id = 'bp-tooltip';
            el.style.display = 'none';
            el.style.position = 'fixed';
            el.style.zIndex = '9999';
            document.body.appendChild(el);
        }
        return el;
    },

    tooltipHTML: function(item) {
        return (item && item.tooltipHtml) ? item.tooltipHtml : '';
    },

    bindTooltips: function(container, items) {
        var list = container.getElementsByTagName('li');
        for (var i = 0; i < list.length && i < items.length; i++) {
            var pic = list[i].getElementsByTagName('a')[0];
            if (!pic) continue;
            this.bindTooltip(pic, items[i]);
        }
    },

    bindTooltip: function(el, item) {
        var tipEl = this.ensureTooltipElem();
        var html = this.tooltipHTML(item);
        if (!html) return;

        el.onmousemove = function(e) {
            var x = e.clientX + 16;
            var y = e.clientY + 16;
            tipEl.innerHTML = html;
            if (x + tipEl.offsetWidth > document.documentElement.clientWidth - 8) {
                x = e.clientX - tipEl.offsetWidth - 16;
            }
            tipEl.style.left = x + 'px';
            tipEl.style.top = y + 'px';
            tipEl.style.display = 'block';
        };
        el.onmouseout = function() {
            tipEl.style.display = 'none';
        };
    },

    renderPagination: function(totalPages) {
        this.ensurePaginationMount();
        var prevImg = document.getElementById('page-prev');
        var nextImg = document.getElementById('page-next');
        var numbersEl = document.getElementById('page-numbers');

        if (prevImg) {
            prevImg.style.cursor = this.currentPage > 1 ? 'pointer' : 'default';
            prevImg.onclick = function() {
                BattlePassUI.changePage(-1);
                return false;
            };
        }
        if (nextImg) {
            nextImg.style.cursor = this.currentPage < totalPages ? 'pointer' : 'default';
            nextImg.onclick = function() {
                BattlePassUI.changePage(1);
                return false;
            };
        }
        if (numbersEl) {
            var html = '';
            for (var p = 1; p <= totalPages; p++) {
                if (p === this.currentPage) {
                    html += '<b class="current">' + p + '</b>';
                } else {
                    html += '<a href="#" onclick="BattlePassUI.goToPage(' + p + '); return false;">' + p + '</a>';
                }
            }
            numbersEl.innerHTML = html;
        }
    },

    changePage: function(delta) {
        var tiers = this.currentData ? this.currentData.tiers : [];
        var totalPages = Math.max(1, Math.ceil(tiers.length / this.itemsPerPage));
        var newPage = this.currentPage + delta;
        if (newPage < 1 || newPage > totalPages) return;
        this.currentPage = newPage;
        this.render(this.currentData);
    },

    goToPage: function(page) {
        var tiers = this.currentData ? this.currentData.tiers : [];
        var totalPages = Math.max(1, Math.ceil(tiers.length / this.itemsPerPage));
        if (page < 1 || page > totalPages) return;
        this.currentPage = page;
        this.render(this.currentData);
    },

    /**
     * Hide/Show premium elements based on PREMIUM_ENABLED config
     */
    updatePremiumVisibility: function() {
        var isEnabled = BP_CONFIG.PREMIUM_ENABLED;

        // Hide/Show gnb tabs (Free Pass / Premium Pass)
        var gnbFree = document.getElementById('gnb-free');
        var gnbPrem = document.getElementById('gnb-prem');
        var gnb = document.getElementById('gnb');

        if (!isEnabled) {
            if (gnbPrem) gnbPrem.style.display = 'none';
            if (gnbFree) gnbFree.style.display = 'none';
            if (gnb) gnb.style.display = 'none';
        } else {
            if (gnbPrem) gnbPrem.style.display = '';
            if (gnbFree) gnbFree.style.display = '';
            if (gnb) gnb.style.display = '';
        }

        // Hide/Show purchase premium button
        var purchaseList = document.getElementById('purchase-list');
        if (purchaseList) {
            var premiumButtons = purchaseList.getElementsByTagName('a');
            for (var i = 0; i < premiumButtons.length; i++) {
                var btn = premiumButtons[i];
                if (btn.innerHTML.indexOf('Purchase Premium') !== -1 ||
                    btn.innerHTML.indexOf('Premium Active') !== -1) {
                    btn.parentNode.parentNode.parentNode.style.display = isEnabled ? '' : 'none';
                }
            }
        }

        // Hide/Show purchase-pass-details (badge, pass type info)
        var purchaseDetails = bpGetElementsByClassName('purchase-pass-details');
        for (var j = 0; j < purchaseDetails.length; j++) {
            purchaseDetails[j].style.display = isEnabled ? '' : 'none';
        }

        // If premium is disabled, force tab to free
        if (!isEnabled) {
            this.currentTab = 'free';
        }
    },

    showPremiumPopup: function() {
        var self = this;
        this.showConfirm('Premium Pass Required', 'This is a Premium Pass reward!<br><br>Purchase Premium Pass for ' + BP_CONFIG.PREMIUM_PRICE + ' Silk to unlock all VIP rewards?', function() {
            self.handlePurchasePremium();
        });
    },

    showError: function(message) {
        this.showAlert('Error', message);
    },

    showAlert: function(title, message) {
        var modal = document.getElementById('alert_modal');
        var titleEl = document.getElementById('alert_title');
        var contentEl = document.getElementById('alert_content');
        var okBtn = document.getElementById('alert_ok');
        var closeBtn = document.getElementById('alert_close');

        if (!modal || !titleEl || !contentEl) return;

        titleEl.innerHTML = bpEscapeHtml(title || 'Notice');
        contentEl.innerHTML = bpEscapeHtml(message || '');

        if (okBtn) okBtn.style.display = 'none';
        if (closeBtn) closeBtn.innerHTML = 'OK';

        var self = this;
        var closeHandler = function() {
            if (closeBtn) closeBtn.onclick = null;
            modal.style.display = 'none';
        };

        if (closeBtn) closeBtn.onclick = closeHandler;
        modal.style.display = 'block';
    },

    showConfirm: function(title, message, onConfirm) {
        var modal = document.getElementById('alert_modal');
        var titleEl = document.getElementById('alert_title');
        var contentEl = document.getElementById('alert_content');
        var okBtn = document.getElementById('alert_ok');
        var closeBtn = document.getElementById('alert_close');

        if (!modal || !titleEl || !contentEl) return;

        titleEl.innerHTML = title || 'Confirm';
        contentEl.innerHTML = message || '';

        if (okBtn) okBtn.style.display = 'inline';
        if (okBtn) okBtn.innerHTML = 'Yes';
        if (closeBtn) closeBtn.innerHTML = 'No';

        var self = this;

        var confirmHandler = function() {
            if (okBtn) okBtn.onclick = null;
            if (closeBtn) closeBtn.onclick = null;
            modal.style.display = 'none';
            if (onConfirm) onConfirm();
        };

        var cancelHandler = function() {
            if (okBtn) okBtn.onclick = null;
            if (closeBtn) closeBtn.onclick = null;
            modal.style.display = 'none';
        };

        if (okBtn) okBtn.onclick = confirmHandler;
        if (closeBtn) closeBtn.onclick = cancelHandler;
        modal.style.display = 'block';
    },

    buildTabs: function() {
        var gnb = document.getElementById('gnb');
        if (!gnb || document.getElementById('tab-free')) return;

        gnb.innerHTML =
            '<li id="gnb-free" class="silk current"><a href="#" id="tab-free">Free Pass</a></li>' +
            '<li id="gnb-prem" class="prem"><a href="#" id="tab-premium">Premium Pass</a></li>';

        // Hide the tab bar until we know premium is enabled (server syncs it on first load)
        if (!BP_CONFIG.PREMIUM_ENABLED) {
            gnb.style.display = 'none';
        }
    },

    ensureTopTierMount: function() {
        if (document.getElementById('top-tier-list')) return;
        var lead = document.getElementById('lead');
        if (!lead) return;

        lead.insertAdjacentHTML('beforeend',
            '<div class="pod hotshop jcarousel-skin-tango">' +
            '<div class="run_">' +
            '<h2>New &amp; Special</h2>' +
            '<div id="mycarousel_" dir="ltr"><ul id="top-tier-list"></ul></div>' +
            '</div>' +
            '</div>');
    },

    ensurePurchaseMount: function() {
        if (document.getElementById('purchase-list')) return;
        var lead = document.getElementById('lead');
        if (!lead) return;

        lead.insertAdjacentHTML('beforeend',
            '<div class="pod hotshop purchase-pass jcarousel-skin-tango">' +
            '<div class="run_">' +
            '<h2>Purchase Points</h2>' +
            '<div id="mycarousel__" dir="ltr"><ul id="purchase-list"></ul></div>' +
            '</div>' +
            '</div>');
    },

    ensurePaginationMount: function() {
        var pagex = document.getElementById('pagex');
        if (!pagex || document.getElementById('page-prev')) return;

        pagex.innerHTML =
            '<a href="#" id="page-prev" onclick="return false;">' +
            '<img src="/in-game/battle-pass/images/btn_prev.gif" border="0" style="vertical-align: middle;" /></a>' +
            '<span id="page-numbers"></span>' +
            '<a href="#" id="page-next" onclick="return false;">' +
            '<img src="/in-game/battle-pass/images/btn_next.gif" border="0" style="vertical-align: middle;" /></a>';
    },

    setupEventListeners: function() {
        var self = this;
        var freeTab = document.getElementById('tab-free');
        var premTab = document.getElementById('tab-premium');

        if (freeTab) {
            freeTab.onclick = function(e) {
                if (window.event) window.event.returnValue = false;
                else if (e && e.preventDefault) e.preventDefault();
                self.currentTab = 'free';
                self.currentPage = 1;
                self.render(self.currentData);
            };
        }

        if (premTab) {
            premTab.onclick = function(e) {
                if (window.event) window.event.returnValue = false;
                else if (e && e.preventDefault) e.preventDefault();
                self.currentTab = 'premium';
                self.currentPage = 1;
                self.render(self.currentData);
            };
        }
    },

    handleClaim: function(tierID, type) {
        var self = this;
        if (type === 'vip' && this.currentData && !this.currentData.record.isPremium) {
            this.showPremiumPopup();
            return;
        }

        BattlePassAPI.claimItem(tierID, type, function(result) {
            if (result.success) {
                self.showAlert('Success', result.itemName + ' x' + result.quantity + ' claimed successfully!');
                self.refresh();
            } else {
                self.showAlert('Error', result.error || result.message || 'Failed to claim item');
            }
        });
    },

    handlePurchasePremium: function() {
        var self = this;
        this.showConfirm('Purchase Premium Pass', 'Purchase Premium Pass for ' + BP_CONFIG.PREMIUM_PRICE + ' Silk?<br><br>This will unlock all VIP rewards!', function() {
            BattlePassAPI.purchasePremium(function(result) {
                if (result.success) {
                    self.showAlert('Success', 'Premium Pass purchased successfully! All VIP rewards unlocked!');
                    self.refresh();
                } else {
                    self.showAlert('Error', result.error || result.message || 'Failed to purchase premium');
                }
            });
        });
    },

    handlePurchasePoints: function() {
        var self = this;
        var qtyInput = document.getElementById('qty');
        if (!qtyInput) return;
        var qty = parseInt(qtyInput.value) || 1;
        var totalCost = qty * BP_CONFIG.SILK_PER_POINT * BP_CONFIG.POINTS_PER_PURCHASE;

        this.showConfirm('Purchase Confirmation', 'Purchase ' + qty + ' Point(s) for ' + totalCost + ' Silk?', function() {
            BattlePassAPI.purchasePoints(qty, function(result) {
                if (result.success) {
                    self.showAlert('Success', result.pointsPurchased + ' Point(s) purchased! Remaining Silk: ' + result.remainingSilk);
                    self.refresh();
                } else {
                    self.showAlert('Error', result.error || result.message || 'Failed to purchase points');
                }
            });
        });
    }
};

// ============================================================
// INITIALIZE
// ============================================================
function initBattlePass() {
    BattlePassUI.init();
}

// IE7 compatible DOM ready
if (document.addEventListener) {
    document.addEventListener('DOMContentLoaded', initBattlePass);
} else if (document.attachEvent) {
    document.attachEvent('onreadystatechange', function() {
        if (document.readyState === 'complete') {
            initBattlePass();
        }
    });
} else {
    window.onload = initBattlePass;
}
        