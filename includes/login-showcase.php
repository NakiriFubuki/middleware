<?php
$showcaseVariant = $showcaseVariant ?? 'login';
$isRegister = $showcaseVariant === 'register';
?>
<div class="login-showcase-content">
    <div class="showcase-brand">
        <span class="showcase-brand-icon"><?= $isRegister ? '🏍️' : '📦' ?></span>
        <span class="showcase-brand-name">PDMS</span>
    </div>

    <p class="login-showcase-badge"><?= $isRegister ? 'Rider Portal' : 'GPS Powered' ?></p>

    <h1 class="login-showcase-title">
        <?= $isRegister ? 'Join the Delivery Fleet' : 'Parcel Delivery Management System' ?>
    </h1>

    <p class="login-showcase-desc">
        <?= $isRegister
            ? 'Register as a rider and get assigned parcels with live GPS tracking on every delivery.'
            : 'Real-time rider tracking, parcel dispatch, and full delivery oversight in one platform.' ?>
    </p>

    <ul class="login-feature-list">
        <li><span>📡</span> Live GPS</li>
        <li><span>🗺️</span> Route Map</li>
        <li><span>📦</span> Parcels</li>
        <li><span>🏍️</span> Riders</li>
    </ul>

    <div class="showcase-visual" aria-hidden="true">
        <div class="showcase-map-card">
            <div class="showcase-map-header">
                <span class="showcase-live">
                    <span class="pulse-dot"></span>
                    Live Tracking
                </span>
                <span class="showcase-map-badge">PDMS</span>
            </div>

            <div class="showcase-map-body">
                <svg class="showcase-route-svg" viewBox="0 0 360 180" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="routeGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" stop-color="#93c5fd"/>
                            <stop offset="100%" stop-color="#ffffff"/>
                        </linearGradient>
                    </defs>
                    <?php for ($i = 0; $i < 12; $i++): ?>
                        <?php for ($j = 0; $j < 6; $j++): ?>
                            <circle cx="<?= 20 + $i * 28 ?>" cy="<?= 18 + $j * 28 ?>" r="1.2" fill="rgba(255,255,255,0.18)"/>
                        <?php endfor; ?>
                    <?php endfor; ?>
                    <path class="route-path" d="M36 140 C 80 140, 90 60, 130 70 S 190 120, 220 90 S 280 40, 324 52" fill="none" stroke="url(#routeGrad)" stroke-width="3" stroke-linecap="round" stroke-dasharray="8 6"/>
                    <circle class="route-pin route-pin-a" cx="36" cy="140" r="7" fill="#ffffff"/>
                    <circle class="route-pin route-pin-b" cx="324" cy="52" r="7" fill="#bfdbfe"/>
                    <g class="route-rider">
                        <circle cx="0" cy="0" r="11" fill="#2563eb" stroke="#ffffff" stroke-width="2"/>
                        <text x="0" y="4" text-anchor="middle" font-size="11">🏍️</text>
                        <animateMotion dur="6s" repeatCount="indefinite" path="M36 140 C 80 140, 90 60, 130 70 S 190 120, 220 90 S 280 40, 324 52"/>
                    </g>
                </svg>

                <div class="showcase-map-pins">
                    <span class="map-pin map-pin-pickup">📍 Pickup</span>
                    <span class="map-pin map-pin-drop">🎯 Drop-off</span>
                </div>
            </div>

            <div class="showcase-capabilities">
                <div class="showcase-cap-item">
                    <span class="cap-icon">📊</span>
                    <span class="cap-label">Dashboard</span>
                </div>
                <div class="showcase-cap-item">
                    <span class="cap-icon">📋</span>
                    <span class="cap-label">Reports</span>
                </div>
                <div class="showcase-cap-item">
                    <span class="cap-icon">🔔</span>
                    <span class="cap-label">Status</span>
                </div>
            </div>
        </div>

        <div class="showcase-float showcase-float-box">📦</div>
        <div class="showcase-float showcase-float-signal">📡</div>
    </div>
</div>
