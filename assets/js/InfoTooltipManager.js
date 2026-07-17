/**
 * Global Info Tooltip System
 * Creates reusable info icons with customizable tooltips
 */

class InfoTooltipManager {
    constructor() {
        this.tooltips = new Map();
        this.init();
    }

    init() {
        // Event delegation for info icon triggers
        $(document).on('mouseenter', '.info-icon-trigger', (e) => {
            const tooltipId = $(e.currentTarget).data('tooltip-id');
            this.show(tooltipId);
        });

        $(document).on('mouseleave', '.info-icon-trigger', (e) => {
            const tooltipId = $(e.currentTarget).data('tooltip-id');
            this.hide(tooltipId);
        });

        // Close tooltips when clicking outside
        $(document).on('click', (e) => {
            if (!$(e.target).closest('.info-icon-wrapper, .info-tooltip').length) {
                this.hideAll();
            }
        });
    }

    /**
     * Create an info icon with tooltip
     * @param {string} tooltipId - Unique identifier for the tooltip
     * @param {Object} config - Configuration object
     */
    create(tooltipId, config = {}) {
        const {
            title = '',
            subtitle = '',
            sections = [],
            warning = '',
            position = 'top-left',
            icon = 'mdi:information-outline'
        } = config;

        // Check if tooltip already exists
        if (this.tooltips.has(tooltipId)) {
            console.warn(`Tooltip with ID "${tooltipId}" already exists. Use update() instead.`);
            return this.getElement(tooltipId);
        }

        // Create icon wrapper
        const $wrapper = $('<span>')
            .addClass('info-icon-wrapper position-relative d-inline-flex');

        // Create icon element
        const $icon = $('<iconify-icon>')
            .attr({
                'icon': icon,
                'data-tooltip-id': tooltipId
            })
            .addClass('info-icon-trigger');

        // Create tooltip
        const $tooltip = $('<div>')
            .attr('id', tooltipId)
            .addClass(`info-tooltip component-theme ${position}`);

        // Build tooltip content
        let tooltipHTML = '';

        // Add title if provided
        if (title) {
            tooltipHTML += `<span class="info-tooltip-title">${this.escapeHtml(title)}</span>`;
        }

        // Add subtitle if provided
        if (subtitle) {
            tooltipHTML += `<span class="info-tooltip-subtitle">${this.escapeHtml(subtitle)}</span>`;
        }

        // Add sections
        if (Array.isArray(sections) && sections.length > 0) {
            sections.forEach(section => {
                tooltipHTML += this.buildSection(section);
            });
        }

        // Add warning if provided
        if (warning) {
            tooltipHTML += `<span class="info-tooltip-warning">⚠️ ${this.escapeHtml(warning)}</span>`;
        }

        $tooltip.html(tooltipHTML);

        // Append to wrapper
        $wrapper.append($icon, $tooltip);

        // Store reference
        this.tooltips.set(tooltipId, {
            $wrapper,
            $tooltip,
            $icon,
            config
        });

        return $wrapper;
    }

    /**
     * Build a section within the tooltip
     * @private
     */
    buildSection(section) {
        const { title, subtitle, items, type = 'list' } = section;
        let html = '<div class="info-tooltip-section">';

        if (title) {
            html += `<span class="info-tooltip-title">${this.escapeHtml(title)}</span>`;
        }

        if (subtitle) {
            html += `<span class="info-tooltip-subtitle">${this.escapeHtml(subtitle)}</span>`;
        }

        if (Array.isArray(items) && items.length > 0) {
            if (type === 'list') {
                html += '<div class="info-tooltip-list">';
                items.forEach(item => {
                    if (typeof item === 'string') {
                        html += `<span class="info-tooltip-list-item">${this.escapeHtml(item)}</span>`;
                    } else if (typeof item === 'object') {
                        html += `<span class="info-tooltip-list-item">
                            ${item.bullet || '•'} 
                            <span${item.highlight ? ' class="info-tooltip-highlight"' : ''}>${this.escapeHtml(item.text)}</span>
                            ${item.subtext ? `<br><span class="text-11 sub-text-color ps-2">${this.escapeHtml(item.subtext)}</span>` : ''}
                        </span>`;
                    }
                });
                html += '</div>';
            }
        }

        html += '</div>';
        return html;
    }

    /**
     * Show tooltip
     * @param {string} tooltipId
     */
    show(tooltipId) {
        const tooltip = this.tooltips.get(tooltipId);
        if (tooltip) {
            tooltip.$tooltip.stop(true).fadeIn(150);
        }
    }

    /**
     * Hide tooltip
     * @param {string} tooltipId
     */
    hide(tooltipId) {
        const tooltip = this.tooltips.get(tooltipId);
        if (tooltip) {
            tooltip.$tooltip.stop(true).fadeOut(150);
        }
    }

    /**
     * Hide all tooltips
     */
    hideAll() {
        this.tooltips.forEach((tooltip) => {
            tooltip.$tooltip.stop(true).fadeOut(150);
        });
    }

    /**
     * Update tooltip content
     * @param {string} tooltipId
     * @param {Object} newConfig
     */
    update(tooltipId, newConfig = {}) {
        const tooltip = this.tooltips.get(tooltipId);
        if (!tooltip) {
            console.error(`Tooltip with ID "${tooltipId}" not found.`);
            return;
        }

        const updatedConfig = { ...tooltip.config, ...newConfig };
        tooltip.config = updatedConfig;

        // Rebuild tooltip content
        let tooltipHTML = '';
        if (updatedConfig.title) {
            tooltipHTML += `<span class="info-tooltip-title">${this.escapeHtml(updatedConfig.title)}</span>`;
        }
        if (updatedConfig.subtitle) {
            tooltipHTML += `<span class="info-tooltip-subtitle">${this.escapeHtml(updatedConfig.subtitle)}</span>`;
        }
        if (Array.isArray(updatedConfig.sections) && updatedConfig.sections.length > 0) {
            updatedConfig.sections.forEach(section => {
                tooltipHTML += this.buildSection(section);
            });
        }
        if (updatedConfig.warning) {
            tooltipHTML += `<span class="info-tooltip-warning">⚠️ ${this.escapeHtml(updatedConfig.warning)}</span>`;
        }

        tooltip.$tooltip.html(tooltipHTML);
    }

    /**
     * Remove tooltip
     * @param {string} tooltipId
     */
    remove(tooltipId) {
        const tooltip = this.tooltips.get(tooltipId);
        if (tooltip) {
            tooltip.$wrapper.remove();
            this.tooltips.delete(tooltipId);
        }
    }

    /**
     * Get tooltip element
     * @param {string} tooltipId
     */
    getElement(tooltipId) {
        const tooltip = this.tooltips.get(tooltipId);
        return tooltip ? tooltip.$wrapper : null;
    }

    /**
     * Escape HTML special characters
     * @private
     */
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
}

// Initialize globally
const InfoTooltip = new InfoTooltipManager();

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = InfoTooltip;
}