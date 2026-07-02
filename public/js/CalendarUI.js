/**
 * CalendarUI - A robust, reusable, and dynamic calendar component.
 */
class CalendarUI {
    constructor(containerSelector, options = {}) {
        this.container = document.querySelector(containerSelector);
        if (!this.container) throw new Error(`CalendarUI: Container not found for selector "${containerSelector}"`);
        
        this.options = Object.assign({
            initialDate: new Date(),
            events: [], // Array of event objects containing at least a 'date' property (YYYY-MM-DD)
            onDateClick: (dateStr, events, cellElement) => {}, // Callback when a date cell is clicked
            onMonthChange: (year, month) => {}, // Callback when month changes (useful for lazy loading)
            renderCellContent: null, // (dateStr, events) => HTML string. If null, uses default rendering.
            renderHeader: true, // Whether to render the default month/nav header
            gridGap: '1px',
            gridLineColor: 'var(--cui-color-border)',
            gridBgColor: 'var(--cui-color-surface)',
            gridEmptyBgColor: 'var(--cui-color-background)',
            cellStyles: {}, // Object mapping date strings to CSS style strings (e.g. { '2026-06-25': 'background: red;' })
            theme: {
                primary: 'var(--color-secondary, #3b82f6)',
                surface: 'var(--color-surface, #ffffff)',
                background: 'var(--color-background, #f3f4f6)',
                border: 'var(--color-border, #e5e7eb)',
                text: 'var(--color-text, #1f2937)',
                textMuted: 'var(--color-text-muted, #6b7280)'
            }
        }, options);
        
        this.currentDate = new Date(this.options.initialDate.getFullYear(), this.options.initialDate.getMonth(), 1);
        this.eventsByDate = this.groupEventsByDate(this.options.events);
        
        this.initStyles();
        this.render();
    }

    groupEventsByDate(events) {
        const grouped = {};
        events.forEach(ev => {
            if (!ev.date) return;
            const dStr = ev.date.substring(0, 10);
            if (!grouped[dStr]) grouped[dStr] = [];
            grouped[dStr].push(ev);
        });
        return grouped;
    }

    setEvents(events) {
        this.options.events = events;
        this.eventsByDate = this.groupEventsByDate(events);
        this.renderGridOnly();
    }

    addEvents(events) {
        this.options.events = this.options.events.concat(events);
        this.eventsByDate = this.groupEventsByDate(this.options.events);
        this.renderGridOnly();
    }

    changeMonth(delta) {
        this.currentDate.setMonth(this.currentDate.getMonth() + delta);
        this.render();
        this.options.onMonthChange(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1);
    }

    setMonth(year, monthIndex) {
        this.currentDate.setFullYear(year);
        this.currentDate.setMonth(monthIndex);
        this.render();
        this.options.onMonthChange(year, monthIndex + 1);
    }

    initStyles() {
        if (document.getElementById('calendar-ui-styles')) return;
        const style = document.createElement('style');
        style.id = 'calendar-ui-styles';
        style.textContent = `
            .cui-wrapper { font-family: inherit; color: var(--cui-color-text); }
            .cui-header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
            .cui-title { font-size: 1.25rem; font-weight: 700; margin: 0; }
            .cui-nav { display: flex; gap: 0.5rem; align-items: center; }
            .cui-btn { padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid var(--cui-color-border); background: var(--cui-color-surface); cursor: pointer; font-weight: 500; transition: all 0.2s; color: var(--cui-color-text); }
            .cui-btn:hover { background: var(--cui-color-background); }
            
            .cui-grid-wrapper { overflow-x: hidden; border: var(--cui-grid-gap, 1px) solid var(--cui-grid-line-color, var(--cui-color-border)); border-radius: 8px; background: transparent; }
            .cui-days-header { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: var(--cui-grid-gap, 1px); background: var(--cui-grid-line-color, var(--cui-color-border)); border-bottom: var(--cui-grid-gap, 1px) solid var(--cui-grid-line-color, var(--cui-color-border)); }
            .cui-day-name { background: var(--cui-color-surface); padding: 0.75rem 0.5rem; text-align: center; font-weight: 600; font-size: 0.85rem; color: var(--cui-color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
            
            .cui-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: var(--cui-grid-gap, 1px); background: var(--cui-grid-line-color, var(--cui-color-border)); }
            .cui-cell { background: var(--cui-grid-bg-color, var(--cui-color-surface)); min-height: 120px; padding: 0.5rem; transition: all 0.2s; position: relative; display: flex; flex-direction: column; }
            .cui-cell.empty { background: var(--cui-grid-empty-bg-color, var(--cui-color-background)); }
            .cui-cell:not(.empty):hover { filter: brightness(0.97); }
            
            .cui-date-num { text-align: right; font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--cui-color-text-muted); }
            .cui-cell.has-events .cui-date-num { color: var(--cui-color-text); }
            .cui-cell.is-today .cui-date-num { color: var(--cui-color-secondary); background: var(--cui-color-secondary); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
            
            .cui-events { display: flex; flex-direction: column; gap: 3px; flex: 1; }
            .cui-event-badge { font-size: 0.75rem; padding: 3px 6px; border-radius: 4px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            
            @media (max-width: 768px) {
                .cui-days-header { grid-template-columns: repeat(7, minmax(0, 1fr)); }
                .cui-day-name { padding: 0.5rem 0.1rem; font-size: 0.65rem; }
                .cui-grid { grid-template-columns: repeat(7, minmax(0, 1fr)); }
                .cui-cell { min-height: 70px; padding: 0.25rem; }
                .cui-date-num { font-size: 0.8rem; margin-bottom: 0.2rem; }
                .cui-event-badge { font-size: 0.6rem; padding: 2px; border-left-width: 1px !important; }
                .cui-header-bar { flex-direction: column; gap: 0.5rem; align-items: center; }
                .cui-nav { width: 100%; justify-content: space-between; gap: 0.5rem; }
                .cui-nav .cui-btn { flex: 1; text-align: center; font-size: 0.85rem; padding: 0.4rem; }
            }
        `;
        document.head.appendChild(style);
    }

    render() {
        this.container.innerHTML = '';
        const wrapper = document.createElement('div');
        wrapper.className = 'cui-wrapper';
        
        // CSS Variables injection for custom themes
        wrapper.style.setProperty('--cui-color-surface', this.options.theme.surface);
        wrapper.style.setProperty('--cui-color-background', this.options.theme.background);
        wrapper.style.setProperty('--cui-color-border', this.options.theme.border);
        wrapper.style.setProperty('--cui-color-text', this.options.theme.text);
        wrapper.style.setProperty('--cui-color-text-muted', this.options.theme.textMuted);
        wrapper.style.setProperty('--cui-color-secondary', this.options.theme.primary);
        wrapper.style.setProperty('--cui-grid-gap', this.options.gridGap);
        wrapper.style.setProperty('--cui-grid-line-color', this.options.gridLineColor);
        wrapper.style.setProperty('--cui-grid-bg-color', this.options.gridBgColor);
        wrapper.style.setProperty('--cui-grid-empty-bg-color', this.options.gridEmptyBgColor);

        if (this.options.renderHeader) {
            const header = document.createElement('div');
            header.className = 'cui-header-bar';
            
            const title = document.createElement('h3');
            title.className = 'cui-title';
            title.textContent = this.currentDate.toLocaleString('default', { month: 'long', year: 'numeric' });
            
            const nav = document.createElement('div');
            nav.className = 'cui-nav';
            
            const prevBtn = document.createElement('button');
            prevBtn.className = 'cui-btn';
            prevBtn.innerHTML = '&larr; Prev';
            prevBtn.onclick = () => this.changeMonth(-1);
            
            const nextBtn = document.createElement('button');
            nextBtn.className = 'cui-btn';
            nextBtn.innerHTML = 'Next &rarr;';
            nextBtn.onclick = () => this.changeMonth(1);
            
            nav.appendChild(prevBtn);
            nav.appendChild(nextBtn);
            header.appendChild(title);
            header.appendChild(nav);
            wrapper.appendChild(header);
        }

        const gridWrapper = document.createElement('div');
        gridWrapper.className = 'cui-grid-wrapper';
        
        // Days header
        const daysHeader = document.createElement('div');
        daysHeader.className = 'cui-days-header';
        ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(day => {
            const el = document.createElement('div');
            el.className = 'cui-day-name';
            el.textContent = day;
            daysHeader.appendChild(el);
        });
        gridWrapper.appendChild(daysHeader);

        // Grid body container
        this.gridBody = document.createElement('div');
        this.gridBody.className = 'cui-grid';
        gridWrapper.appendChild(this.gridBody);
        
        wrapper.appendChild(gridWrapper);
        this.container.appendChild(wrapper);

        this.renderGridOnly();
    }

    renderGridOnly() {
        if (!this.gridBody) return;
        this.gridBody.innerHTML = '';
        
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth() + 1;
        const firstDay = new Date(year, month - 1, 1).getDay();
        const daysInMonth = new Date(year, month, 0).getDate();
        
        const todayStr = new Date().toISOString().substring(0,10);
        
        for (let i = 0; i < firstDay; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.className = 'cui-cell empty';
            this.gridBody.appendChild(emptyCell);
        }
        
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dayEvents = this.eventsByDate[dateStr] || [];
            
            const cell = document.createElement('div');
            cell.className = 'cui-cell' + (dayEvents.length > 0 ? ' has-events' : '');
            if (dateStr === todayStr) cell.classList.add('is-today');
            
            // Apply custom specific cell styles if provided
            if (this.options.cellStyles && this.options.cellStyles[dateStr]) {
                cell.style.cssText += ';' + this.options.cellStyles[dateStr];
            }
            
            if (this.options.renderCellContent) {
                cell.innerHTML = this.options.renderCellContent(dateStr, dayEvents);
            } else {
                // Default Rendering
                const dateNum = document.createElement('div');
                dateNum.className = 'cui-date-num';
                dateNum.textContent = day;
                cell.appendChild(dateNum);
                
                if (dayEvents.length > 0) {
                    const eventsContainer = document.createElement('div');
                    eventsContainer.className = 'cui-events';
                    
                    const maxToShow = 3;
                    for (let i = 0; i < Math.min(dayEvents.length, maxToShow); i++) {
                        const ev = dayEvents[i];
                        const badge = document.createElement('div');
                        badge.className = 'cui-event-badge';
                        const color = ev.color || this.options.theme.primary;
                        badge.style.backgroundColor = `${color}20`;
                        badge.style.color = color;
                        badge.style.borderLeft = `2px solid ${color}`;
                        badge.textContent = ev.title || 'Event';
                        eventsContainer.appendChild(badge);
                    }
                    
                    if (dayEvents.length > maxToShow) {
                        const more = document.createElement('div');
                        more.style.fontSize = '0.75rem';
                        more.style.fontWeight = '600';
                        more.style.color = 'var(--color-text-muted)';
                        more.style.marginTop = '4px';
                        more.textContent = `+${dayEvents.length - maxToShow} more`;
                        eventsContainer.appendChild(more);
                    }
                    
                    cell.appendChild(eventsContainer);
                }
            }
            
            if (this.options.onDateClick) {
                cell.style.cursor = 'pointer';
                cell.onclick = (e) => this.options.onDateClick(dateStr, dayEvents, cell);
            }
            
            this.gridBody.appendChild(cell);
        }
        
        // Add trailing empty cells to complete the 7-column grid
        const totalCells = firstDay + daysInMonth;
        const trailingDays = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
        for (let i = 0; i < trailingDays; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.className = 'cui-cell empty';
            this.gridBody.appendChild(emptyCell);
        }
    }
}

// Expose globally
window.CalendarUI = CalendarUI;
