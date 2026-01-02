/**
 * Sistema de renovación automática de sesiones
 * Mantiene la sesión activa para evitar logouts automáticos
 */

class SessionRenewer {
    constructor() {
        this.intervalId = null;
        this.renewIntervalMinutes = 30; // Renovar cada 30 minutos
        this.maxFailures = 3;
        this.currentFailures = 0;
        this.isActive = false;
        
        this.init();
    }
    
    init() {
        // Solo iniciar si hay una sesión activa
        if (this.hasActiveSession()) {
            this.start();
            this.setupVisibilityListener();
            this.setupActivityListener();
        }
    }
    
    hasActiveSession() {
        // Verificar si hay indicios de sesión activa
        return document.body.classList.contains('logged-in') || 
               document.querySelector('[data-user-id]') || 
               window.location.pathname.includes('/admin/') ||
               window.location.pathname.includes('/cajero/') ||
               window.location.pathname.includes('/vendedor/') ||
               window.location.pathname.includes('/superadmin/');
    }
    
    start() {
        if (this.isActive) return;
        
        this.isActive = true;
        console.log('🔄 Sistema de renovación de sesión iniciado');
        
        // Renovar inmediatamente
        this.renewSession();
        
        // Configurar intervalo periódico
        this.intervalId = setInterval(() => {
            this.renewSession();
        }, this.renewIntervalMinutes * 60 * 1000);
    }
    
    stop() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
        this.isActive = false;
        console.log('⏸️ Sistema de renovación de sesión detenido');
    }
    
    async renewSession() {
        try {
            const response = await fetch('/api/renovar_sesion.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.currentFailures = 0;
                console.log('✅ Sesión renovada:', data.formatted_time);
            } else {
                this.handleFailure(data.message);
            }
            
        } catch (error) {
            this.handleFailure('Error de red: ' + error.message);
        }
    }
    
    handleFailure(message) {
        this.currentFailures++;
        console.warn('⚠️ Error renovando sesión:', message, `(${this.currentFailures}/${this.maxFailures})`);
        
        if (this.currentFailures >= this.maxFailures) {
            console.error('❌ Máximo de fallos alcanzado. Deteniendo renovación automática.');
            this.stop();
            
            // Mostrar notificación al usuario
            this.showSessionWarning();
        }
    }
    
    showSessionWarning() {
        // Crear notificación discreta
        const notification = document.createElement('div');
        notification.className = 'alert alert-warning session-warning';
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 300px;
            opacity: 0;
            transition: opacity 0.3s ease;
        `;
        
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="bi bi-clock-history me-2"></i>
                <div class="flex-grow-1">
                    <strong>Sesión próxima a expirar</strong><br>
                    <small>Guarda tu trabajo y actualiza la página</small>
                </div>
                <button type="button" class="btn-close btn-close-sm" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Mostrar con animación
        setTimeout(() => {
            notification.style.opacity = '1';
        }, 100);
        
        // Auto-ocultar después de 10 segundos
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            }
        }, 10000);
    }
    
    setupVisibilityListener() {
        // Renovar cuando la página vuelva a estar visible
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && this.isActive) {
                console.log('📄 Página visible de nuevo, renovando sesión...');
                this.renewSession();
            }
        });
    }
    
    setupActivityListener() {
        // Renovar cuando hay actividad del usuario
        let activityTimer = null;
        const activityEvents = ['click', 'keydown', 'scroll', 'mousemove'];
        
        const handleActivity = () => {
            if (activityTimer) clearTimeout(activityTimer);
            
            activityTimer = setTimeout(() => {
                if (this.isActive) {
                    this.renewSession();
                }
            }, 5 * 60 * 1000); // 5 minutos después de actividad
        };
        
        activityEvents.forEach(event => {
            document.addEventListener(event, handleActivity, { passive: true });
        });
    }
}

// Inicializar automáticamente cuando se carga la página
document.addEventListener('DOMContentLoaded', () => {
    // Esperar un poco para que se cargue todo
    setTimeout(() => {
        window.sessionRenewer = new SessionRenewer();
    }, 1000);
});

// Limpiar al cerrar/cambiar página
window.addEventListener('beforeunload', () => {
    if (window.sessionRenewer) {
        window.sessionRenewer.stop();
    }
});