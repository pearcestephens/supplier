/**
 * Neuro AI Assistant Integration
 * 
 * AI-powered supplier portal assistant
 * Context-aware, conversational interface
 * 
 * @package CIS\Supplier\Assets
 * @version 2.0.0
 */

(function() {
    'use strict';
    
    const NEURO_CONFIG = {
        endpoint: '/supplier/api/neuro-chat.php',
        model: 'neuro-large',
        sessionKey: 'neuro_session_id',
        contextWindow: 10, // Last 10 messages
    };
    
    class NeuroAIAssistant {
        constructor() {
            this.sessionID = this.getSessionID();
            this.messages = [];
            this.isOpen = false;
            
            this.init();
        }
        
        init() {
            // Event listeners
            $('#neuro-toggle').on('click', () => this.toggle());
            $('#neuro-close').on('click', () => this.close());
            $('#neuro-send').on('click', () => this.sendMessage());
            $('#neuro-input').on('keypress', (e) => {
                if (e.which === 13) {
                    this.sendMessage();
                }
            });
            
            // Load previous session
            this.loadSession();
            
            console.log('Neuro AI Assistant initialized', this.sessionID);
        }
        
        getSessionID() {
            let sessionID = localStorage.getItem(NEURO_CONFIG.sessionKey);
            
            if (!sessionID) {
                sessionID = 'neuro_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                localStorage.setItem(NEURO_CONFIG.sessionKey, sessionID);
            }
            
            return sessionID;
        }
        
        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }
        
        open() {
            $('#neuro-chat-panel').fadeIn(200);
            this.isOpen = true;
            $('#neuro-input').focus();
            
            // Welcome message if empty
            if (this.messages.length === 0) {
                this.addMessage('system', 'Hello! I\'m Neuro, your AI assistant. Ask me anything about your orders, warranty claims, or reports.');
            }
        }
        
        close() {
            $('#neuro-chat-panel').fadeOut(200);
            this.isOpen = false;
        }
        
        async sendMessage() {
            const input = $('#neuro-input').val().trim();
            
            if (!input) {
                return;
            }
            
            // Add user message
            this.addMessage('user', input);
            $('#neuro-input').val('');
            
            // Show typing indicator
            this.showTyping();
            
            try {
                const response = await this.callNeuroAPI(input);
                
                // Remove typing indicator
                this.hideTyping();
                
                // Add AI response
                this.addMessage('ai', response.message);
                
                // Save session
                this.saveSession();
                
            } catch (error) {
                this.hideTyping();
                this.addMessage('error', 'Sorry, I encountered an error. Please try again.');
                console.error('Neuro API error:', error);
            }
        }
        
        async callNeuroAPI(message) {
            // Get current page context
            const context = this.getCurrentContext();
            
            const response = await fetch(NEURO_CONFIG.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    session_id: this.sessionID,
                    message: message,
                    context: context,
                    history: this.messages.slice(-NEURO_CONFIG.contextWindow),
                }),
            });
            
            if (!response.ok) {
                throw new Error('API request failed');
            }
            
            return await response.json();
        }
        
        getCurrentContext() {
            // Extract page context
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'dashboard';
            
            const context = {
                page: tab,
                url: window.location.pathname,
                timestamp: new Date().toISOString(),
            };
            
            // Add tab-specific context
            if (tab === 'orders') {
                context.year = urlParams.get('year') || '';
                context.quarter = urlParams.get('quarter') || '';
            } else if (tab === 'warranty') {
                context.fault_id = urlParams.get('fault_id') || '';
            }
            
            return context;
        }
        
        addMessage(type, content) {
            const message = {
                type: type,
                content: content,
                timestamp: Date.now(),
            };
            
            this.messages.push(message);
            
            // Render message
            const messageHTML = this.renderMessage(message);
            $('#neuro-messages').append(messageHTML);
            
            // Scroll to bottom
            this.scrollToBottom();
        }
        
        renderMessage(message) {
            const timestamp = new Date(message.timestamp).toLocaleTimeString();
            
            let html = '<div class="neuro-message neuro-' + message.type + '">';
            
            if (message.type === 'user') {
                html += '<div class="message-content">' + this.escapeHTML(message.content) + '</div>';
                html += '<div class="message-meta">You · ' + timestamp + '</div>';
            } else if (message.type === 'ai') {
                html += '<div class="message-avatar"><i class="fas fa-robot"></i></div>';
                html += '<div class="message-content">' + this.markdownToHTML(message.content) + '</div>';
                html += '<div class="message-meta">Neuro · ' + timestamp + '</div>';
            } else if (message.type === 'system') {
                html += '<div class="message-content system-message">' + this.escapeHTML(message.content) + '</div>';
            } else if (message.type === 'error') {
                html += '<div class="message-content error-message"><i class="fas fa-exclamation-triangle"></i> ' + this.escapeHTML(message.content) + '</div>';
            }
            
            html += '</div>';
            
            return html;
        }
        
        showTyping() {
            const typingHTML = '<div class="neuro-message neuro-typing" id="neuro-typing">' +
                '<div class="message-avatar"><i class="fas fa-robot"></i></div>' +
                '<div class="message-content">' +
                    '<div class="typing-indicator">' +
                        '<span></span><span></span><span></span>' +
                    '</div>' +
                '</div>' +
                '</div>';
            
            $('#neuro-messages').append(typingHTML);
            this.scrollToBottom();
        }
        
        hideTyping() {
            $('#neuro-typing').remove();
        }
        
        scrollToBottom() {
            const messagesDiv = $('#neuro-messages')[0];
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }
        
        saveSession() {
            const sessionData = {
                session_id: this.sessionID,
                messages: this.messages,
                timestamp: Date.now(),
            };
            
            localStorage.setItem('neuro_session_data', JSON.stringify(sessionData));
        }
        
        loadSession() {
            try {
                const sessionData = localStorage.getItem('neuro_session_data');
                
                if (sessionData) {
                    const data = JSON.parse(sessionData);
                    
                    // Only load if less than 24 hours old
                    if (Date.now() - data.timestamp < 24 * 60 * 60 * 1000) {
                        this.messages = data.messages || [];
                        
                        // Render messages
                        this.messages.forEach(msg => {
                            const html = this.renderMessage(msg);
                            $('#neuro-messages').append(html);
                        });
                    }
                }
            } catch (e) {
                console.error('Error loading session:', e);
            }
        }
        
        escapeHTML(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
        
        markdownToHTML(markdown) {
            // Simple markdown parser
            let html = this.escapeHTML(markdown);
            
            // Bold
            html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
            
            // Italic
            html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
            
            // Code
            html = html.replace(/`(.+?)`/g, '<code>$1</code>');
            
            // Line breaks
            html = html.replace(/\n/g, '<br>');
            
            return html;
        }
    }
    
    // Initialize when DOM ready
    $(document).ready(() => {
        window.neuroAI = new NeuroAIAssistant();
    });
    
    // Global function for quick access
    window.openNeuroAI = function() {
        if (window.neuroAI) {
            window.neuroAI.open();
        }
    };
    
})();
