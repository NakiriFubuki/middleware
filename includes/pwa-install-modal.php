<?php $pwaIconUrl = baseUrl('assets/icons/pwa-icon.svg'); ?>
<div id="pwaInstallOverlay" class="pwa-install-overlay" role="dialog" aria-modal="true" aria-labelledby="pwaInstallTitle" hidden>
    <div class="pwa-install-modal">
        <div class="pwa-install-header">
            <img id="pwaInstallIcon" class="pwa-install-icon" src="<?= sanitize($pwaIconUrl) ?>" data-src="<?= sanitize($pwaIconUrl) ?>" alt="PDMS" width="52" height="52">
            <div>
                <h3 id="pwaInstallTitle">Install PDMS App</h3>
                <p>Parcel Delivery Management System</p>
            </div>
        </div>
        <p id="pwaInstallBody" class="pwa-install-body"></p>
        <div class="pwa-install-actions">
            <button type="button" class="btn btn-outline" id="pwaInstallDismiss">Maybe Later</button>
            <button type="button" class="btn btn-primary" id="pwaInstallBtn">Install Now</button>
        </div>
    </div>
</div>
