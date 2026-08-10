/**
 * GPS route cleanup — reduce jitter and zig-zag on map display.
 */
const RouteUtils = {
    distanceMeters(lat1, lon1, lat2, lon2) {
        const earth = 6371000;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) ** 2
            + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon / 2) ** 2;
        return earth * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    },

    bearing(lat1, lon1, lat2, lon2) {
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const y = Math.sin(dLon) * Math.cos(lat2 * Math.PI / 180);
        const x = Math.cos(lat1 * Math.PI / 180) * Math.sin(lat2 * Math.PI / 180)
            - Math.sin(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.cos(dLon);
        return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
    },

    bearingDelta(a, b) {
        let diff = Math.abs(a - b) % 360;
        return diff > 180 ? 360 - diff : diff;
    },

    historyToPoints(history) {
        return (history || [])
            .map(h => [parseFloat(h.latitude), parseFloat(h.longitude)])
            .filter(p => !Number.isNaN(p[0]) && !Number.isNaN(p[1]));
    },

    removeJitter(points, options = {}) {
        const maxSegment = options.maxSegment ?? 35;
        const minTurn = options.minTurn ?? 115;

        if (points.length < 3) return points.slice();

        const cleaned = [points[0]];

        for (let i = 1; i < points.length - 1; i++) {
            const prev = cleaned[cleaned.length - 1];
            const curr = points[i];
            const next = points[i + 1];

            const d1 = this.distanceMeters(prev[0], prev[1], curr[0], curr[1]);
            const d2 = this.distanceMeters(curr[0], curr[1], next[0], next[1]);

            if (d1 < maxSegment && d2 < maxSegment) {
                const b1 = this.bearing(prev[0], prev[1], curr[0], curr[1]);
                const b2 = this.bearing(curr[0], curr[1], next[0], next[1]);
                if (this.bearingDelta(b1, b2) >= minTurn) {
                    continue;
                }
            }

            cleaned.push(curr);
        }

        cleaned.push(points[points.length - 1]);
        return cleaned;
    },

    simplify(points, minGapMeters = 10) {
        if (points.length < 2) return points.slice();

        const simplified = [points[0]];

        for (let i = 1; i < points.length; i++) {
            const last = simplified[simplified.length - 1];
            const curr = points[i];
            const dist = this.distanceMeters(last[0], last[1], curr[0], curr[1]);

            if (i === points.length - 1 || dist >= minGapMeters) {
                simplified.push(curr);
            }
        }

        return simplified;
    },

    smooth(points, windowSize = 3) {
        if (points.length < 3) return points.slice();

        const result = [points[0]];
        const half = Math.floor(windowSize / 2);

        for (let i = 1; i < points.length - 1; i++) {
            let lat = 0;
            let lng = 0;
            let count = 0;

            for (let j = Math.max(0, i - half); j <= Math.min(points.length - 1, i + half); j++) {
                lat += points[j][0];
                lng += points[j][1];
                count++;
            }

            result.push([lat / count, lng / count]);
        }

        result.push(points[points.length - 1]);
        return result;
    },

    cleanRoute(points) {
        if (!points || points.length < 2) return points || [];

        let cleaned = this.removeJitter(points);
        cleaned = this.simplify(cleaned, 8);
        if (cleaned.length >= 3) {
            cleaned = this.smooth(cleaned, 3);
        }
        return cleaned.length >= 2 ? cleaned : points;
    }
};
