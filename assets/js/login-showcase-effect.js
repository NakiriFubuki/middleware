/**
 * Logistics showcase overlay — global network glow for login panel
 */
const LoginShowcaseEffect = {
    canvas: null,
    ctx: null,
    container: null,
    animationId: null,
    nodes: [],
    streaks: [],
    maxNodes: 42,
    linkDistance: 130,

    init() {
        this.container = document.getElementById('showcaseCanvas');
        if (!this.container || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        this.canvas = document.createElement('canvas');
        this.canvas.className = 'showcase-canvas';
        this.container.appendChild(this.canvas);
        this.ctx = this.canvas.getContext('2d');

        this.resize();
        this.createNodes();
        this.createStreaks();
        this.animate();

        window.addEventListener('resize', () => {
            this.resize();
            this.createNodes();
            this.createStreaks();
        });
    },

    resize() {
        if (!this.canvas || !this.container) {
            return;
        }

        const rect = this.container.getBoundingClientRect();
        this.canvas.width = Math.max(1, Math.floor(rect.width));
        this.canvas.height = Math.max(1, Math.floor(rect.height));
    },

    createNodes() {
        const width = this.canvas?.width || 1;
        const height = this.canvas?.height || 1;
        const count = Math.min(this.maxNodes, Math.max(24, Math.floor((width * height) / 18000)));
        this.nodes = [];

        for (let i = 0; i < count; i++) {
            this.nodes.push({
                x: Math.random() * width,
                y: Math.random() * height,
                radius: Math.random() * 1.8 + 1.2,
                pulse: Math.random() * Math.PI * 2,
                pulseSpeed: Math.random() * 0.02 + 0.01,
                tone: Math.random() > 0.55 ? 'cyan' : 'amber'
            });
        }
    },

    createStreaks() {
        const width = this.canvas?.width || 1;
        const height = this.canvas?.height || 1;
        this.streaks = [];

        for (let i = 0; i < 5; i++) {
            this.streaks.push({
                x: Math.random() * width,
                y: Math.random() * height * 0.75 + height * 0.1,
                length: Math.random() * 80 + 40,
                speed: Math.random() * 0.9 + 0.4,
                opacity: Math.random() * 0.18 + 0.08
            });
        }
    },

    nodeColor(tone, alpha) {
        return tone === 'amber'
            ? `rgba(251, 191, 36, ${alpha})`
            : `rgba(125, 211, 252, ${alpha})`;
    },

    drawLinks() {
        const nodes = this.nodes;
        for (let i = 0; i < nodes.length; i++) {
            for (let j = i + 1; j < nodes.length; j++) {
                const dx = nodes[i].x - nodes[j].x;
                const dy = nodes[i].y - nodes[j].y;
                const dist = Math.hypot(dx, dy);

                if (dist > this.linkDistance) {
                    continue;
                }

                const alpha = (1 - dist / this.linkDistance) * 0.22;
                this.ctx.beginPath();
                this.ctx.moveTo(nodes[i].x, nodes[i].y);
                this.ctx.lineTo(nodes[j].x, nodes[j].y);
                this.ctx.strokeStyle = `rgba(147, 197, 253, ${alpha})`;
                this.ctx.lineWidth = 1;
                this.ctx.stroke();
            }
        }
    },

    drawNodes() {
        this.nodes.forEach((node) => {
            node.pulse += node.pulseSpeed;
            const glow = 0.45 + Math.sin(node.pulse) * 0.25;
            const radius = node.radius + Math.sin(node.pulse) * 0.4;

            this.ctx.beginPath();
            this.ctx.arc(node.x, node.y, radius * 2.4, 0, Math.PI * 2);
            this.ctx.fillStyle = this.nodeColor(node.tone, glow * 0.18);
            this.ctx.fill();

            this.ctx.beginPath();
            this.ctx.arc(node.x, node.y, radius, 0, Math.PI * 2);
            this.ctx.fillStyle = this.nodeColor(node.tone, glow);
            this.ctx.fill();
        });
    },

    drawStreaks() {
        const width = this.canvas.width;
        const height = this.canvas.height;

        this.streaks.forEach((streak) => {
            streak.x += streak.speed;
            if (streak.x - streak.length > width) {
                streak.x = -streak.length;
                streak.y = Math.random() * height * 0.75 + height * 0.1;
            }

            const gradient = this.ctx.createLinearGradient(
                streak.x - streak.length,
                streak.y,
                streak.x,
                streak.y
            );
            gradient.addColorStop(0, `rgba(255, 255, 255, 0)`);
            gradient.addColorStop(0.5, `rgba(191, 219, 254, ${streak.opacity})`);
            gradient.addColorStop(1, `rgba(255, 255, 255, 0)`);

            this.ctx.beginPath();
            this.ctx.moveTo(streak.x - streak.length, streak.y);
            this.ctx.lineTo(streak.x, streak.y);
            this.ctx.strokeStyle = gradient;
            this.ctx.lineWidth = 1.5;
            this.ctx.stroke();
        });
    },

    animate() {
        if (!this.ctx || !this.canvas) {
            return;
        }

        const { width, height } = this.canvas;
        this.ctx.clearRect(0, 0, width, height);

        this.drawLinks();
        this.drawStreaks();
        this.drawNodes();

        this.animationId = requestAnimationFrame(() => this.animate());
    },

    destroy() {
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
        }
    }
};

document.addEventListener('DOMContentLoaded', () => LoginShowcaseEffect.init());
