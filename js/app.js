/**
 * RADIUS Remote Client - JavaScript Application
 */

const API_URL = 'api.php';
let currentPage = 1;
let searchQuery = '';

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    initTabs();
    initUserManagement();
    initStats();
    initSessions();
    initWebhooks();
    initTestConnection();
});

/**
 * Sistema de Tabs
 */
function initTabs() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const tabName = btn.dataset.tab;

            // Actualizar botones
            tabButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // Actualizar contenidos
            tabContents.forEach(content => {
                content.classList.remove('active');
            });

            const activeTab = document.getElementById(`${tabName}-tab`);
            if (activeTab) {
                activeTab.classList.add('active');

                // Cargar datos según el tab
                switch(tabName) {
                    case 'users':
                        loadUsers();
                        break;
                    case 'stats':
                        loadStats();
                        break;
                    case 'sessions':
                        loadSessions();
                        break;
                    case 'webhooks':
                        loadWebhooks();
                        break;
                }
            }
        });
    });

    // Cargar usuarios al inicio
    loadUsers();
}

/**
 * Gestión de Usuarios
 */
function initUserManagement() {
    // Búsqueda
    const searchInput = document.getElementById('search-users');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchQuery = e.target.value;
                currentPage = 1;
                loadUsers();
            }, 300);
        });
    }

    // Botón crear usuario
    const btnCreate = document.getElementById('btn-create-user');
    if (btnCreate) {
        btnCreate.addEventListener('click', () => openUserModal());
    }

    // Botón exportar CSV
    const btnExport = document.getElementById('btn-export-csv');
    if (btnExport) {
        btnExport.addEventListener('click', () => exportCSV());
    }

    // Modal
    const modal = document.getElementById('modal-user');
    const closeButtons = modal.querySelectorAll('.modal-close');

    closeButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            modal.classList.remove('active');
        });
    });

    // Click fuera del modal
    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });

    // Formulario
    const form = document.getElementById('form-user');
    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            saveUser();
        });
    }

    // Generar password
    const btnGenPass = document.getElementById('btn-generate-password');
    if (btnGenPass) {
        btnGenPass.addEventListener('click', () => {
            const password = generatePassword();
            document.getElementById('password').value = password;
        });
    }
}

/**
 * Cargar usuarios
 */
async function loadUsers() {
    const tbody = document.getElementById('users-tbody');
    tbody.innerHTML = '<tr><td colspan="7" class="loading">Cargando usuarios...</td></tr>';

    try {
        const response = await fetch(`${API_URL}/users?page=${currentPage}&search=${encodeURIComponent(searchQuery)}`);
        const result = await response.json();

        if (result.success) {
            displayUsers(result.data.users);
            displayPagination(result.data.pagination);
        } else {
            showError('Error al cargar usuarios: ' + result.error);
        }
    } catch (error) {
        showError('Error de conexión: ' + error.message);
    }
}

/**
 * Mostrar usuarios en la tabla
 */
function displayUsers(users) {
    const tbody = document.getElementById('users-tbody');

    if (users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No se encontraron usuarios</td></tr>';
        return;
    }

    tbody.innerHTML = users.map(user => `
        <tr>
            <td><strong>${escapeHtml(user.username)}</strong></td>
            <td>${escapeHtml(user.plan || '-')}</td>
            <td>${escapeHtml(user.bandwidth_up || '-')}</td>
            <td>${escapeHtml(user.bandwidth_down || '-')}</td>
            <td>
                <span class="connection-status ${user.last_connection ? 'connected' : 'disconnected'}">
                    ${user.last_connection ? 'Activo' : 'Inactivo'}
                </span>
            </td>
            <td>${user.last_connection ? formatDate(user.last_connection) : 'Nunca'}</td>
            <td>
                <button class="action-btn action-btn-edit" onclick="editUser('${escapeHtml(user.username)}')">
                    ✏️ Editar
                </button>
                <button class="action-btn action-btn-history" onclick="viewHistory('${escapeHtml(user.username)}')">
                    📊 Historial
                </button>
                <button class="action-btn action-btn-delete" onclick="deleteUser('${escapeHtml(user.username)}')">
                    🗑️ Eliminar
                </button>
            </td>
        </tr>
    `).join('');
}

/**
 * Mostrar paginación
 */
function displayPagination(pagination) {
    const container = document.getElementById('pagination');

    if (pagination.pages <= 1) {
        container.innerHTML = '';
        return;
    }

    container.innerHTML = `
        <button ${pagination.page <= 1 ? 'disabled' : ''} onclick="changePage(${pagination.page - 1})">
            ‹ Anterior
        </button>
        <span class="page-info">
            Página ${pagination.page} de ${pagination.pages}
        </span>
        <button ${pagination.page >= pagination.pages ? 'disabled' : ''} onclick="changePage(${pagination.page + 1})">
            Siguiente ›
        </button>
    `;
}

/**
 * Cambiar página
 */
function changePage(page) {
    currentPage = page;
    loadUsers();
}

/**
 * Abrir modal de usuario
 */
function openUserModal(user = null) {
    const modal = document.getElementById('modal-user');
    const title = document.getElementById('modal-user-title');
    const form = document.getElementById('form-user');

    form.reset();

    if (user) {
        title.textContent = 'Editar Usuario';
        document.getElementById('user-id').value = user.username;
        document.getElementById('username').value = user.username;
        document.getElementById('username').disabled = true;
        document.getElementById('password').value = user.password || '';
        document.getElementById('bandwidth_up').value = user.bandwidth_up || '';
        document.getElementById('bandwidth_down').value = user.bandwidth_down || '';
        document.getElementById('plan').value = user.plan || '';
    } else {
        title.textContent = 'Crear Usuario';
        document.getElementById('user-id').value = '';
        document.getElementById('username').disabled = false;
    }

    modal.classList.add('active');
}

/**
 * Guardar usuario
 */
async function saveUser() {
    const userId = document.getElementById('user-id').value;
    const data = {
        username: document.getElementById('username').value,
        password: document.getElementById('password').value,
        bandwidth_up: document.getElementById('bandwidth_up').value,
        bandwidth_down: document.getElementById('bandwidth_down').value,
        plan: document.getElementById('plan').value
    };

    try {
        const isEdit = userId !== '';
        const url = isEdit ? `${API_URL}/user?username=${encodeURIComponent(userId)}` : `${API_URL}/users`;
        const method = isEdit ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showSuccess(isEdit ? 'Usuario actualizado correctamente' : 'Usuario creado correctamente');
            document.getElementById('modal-user').classList.remove('active');
            loadUsers();
        } else {
            showError('Error: ' + result.error);
        }
    } catch (error) {
        showError('Error de conexión: ' + error.message);
    }
}

/**
 * Editar usuario
 */
async function editUser(username) {
    try {
        const response = await fetch(`${API_URL}/user?username=${encodeURIComponent(username)}`);
        const result = await response.json();

        if (result.success) {
            openUserModal(result.data);
        } else {
            showError('Error al cargar usuario: ' + result.error);
        }
    } catch (error) {
        showError('Error de conexión: ' + error.message);
    }
}

/**
 * Eliminar usuario
 */
async function deleteUser(username) {
    if (!confirm(`¿Estás seguro de eliminar el usuario "${username}"?`)) {
        return;
    }

    try {
        const response = await fetch(`${API_URL}/user?username=${encodeURIComponent(username)}`, {
            method: 'DELETE'
        });

        const result = await response.json();

        if (result.success) {
            showSuccess('Usuario eliminado correctamente');
            loadUsers();
        } else {
            showError('Error al eliminar: ' + result.error);
        }
    } catch (error) {
        showError('Error de conexión: ' + error.message);
    }
}

/**
 * Ver historial de usuario
 */
async function viewHistory(username) {
    try {
        const response = await fetch(`${API_URL}/history?username=${encodeURIComponent(username)}`);
        const result = await response.json();

        if (result.success) {
            displayHistoryModal(username, result.data);
        } else {
            showError('Error al cargar historial: ' + result.error);
        }
    } catch (error) {
        showError('Error de conexión: ' + error.message);
    }
}

/**
 * Mostrar modal de historial
 */
function displayHistoryModal(username, history) {
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 900px;">
            <span class="modal-close" onclick="this.closest('.modal').remove()">&times;</span>
            <h2>Historial de ${escapeHtml(username)}</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Duración</th>
                        <th>Descarga</th>
                        <th>Subida</th>
                        <th>IP</th>
                        <th>NAS</th>
                    </tr>
                </thead>
                <tbody>
                    ${history.map(record => `
                        <tr>
                            <td>${formatDate(record.acctstarttime)}</td>
                            <td>${record.acctstoptime ? formatDate(record.acctstoptime) : 'En curso'}</td>
                            <td>${record.duration_formatted || '-'}</td>
                            <td>${record.download_formatted}</td>
                            <td>${record.upload_formatted}</td>
                            <td>${record.framedipaddress || '-'}</td>
                            <td>${record.nasipaddress || '-'}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;

    document.body.appendChild(modal);

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
}

/**
 * Exportar CSV
 */
function exportCSV() {
    window.location.href = `${API_URL}/export`;
}

/**
 * Estadísticas
 */
function initStats() {
    // Se carga cuando se activa el tab
}

async function loadStats() {
    try {
        const response = await fetch(`${API_URL}/stats`);
        const result = await response.json();

        if (result.success) {
            document.getElementById('stat-total-users').textContent = result.data.total_users;
            document.getElementById('stat-active-sessions').textContent = result.data.active_sessions;
            document.getElementById('stat-today-connections').textContent = result.data.today_connections;
            document.getElementById('stat-today-traffic').textContent = result.data.today_traffic_formatted;

            loadBandwidthChart();
        }
    } catch (error) {
        showError('Error al cargar estadísticas: ' + error.message);
    }
}

async function loadBandwidthChart() {
    try {
        const response = await fetch(`${API_URL}/bandwidth-stats`);
        const result = await response.json();

        if (result.success) {
            // Aquí puedes integrar Chart.js o cualquier otra librería de gráficos
            console.log('Bandwidth stats:', result.data);
        }
    } catch (error) {
        console.error('Error loading bandwidth stats:', error);
    }
}

/**
 * Sesiones Activas
 */
function initSessions() {
    const btnRefresh = document.getElementById('btn-refresh-sessions');
    if (btnRefresh) {
        btnRefresh.addEventListener('click', () => loadSessions());
    }
}

async function loadSessions() {
    const tbody = document.getElementById('sessions-tbody');
    tbody.innerHTML = '<tr><td colspan="7" class="loading">Cargando sesiones...</td></tr>';

    try {
        const response = await fetch(`${API_URL}/sessions`);
        const result = await response.json();

        if (result.success) {
            if (result.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center">No hay sesiones activas</td></tr>';
            } else {
                tbody.innerHTML = result.data.map(session => `
                    <tr>
                        <td><strong>${escapeHtml(session.username)}</strong></td>
                        <td>${session.framedipaddress || '-'}</td>
                        <td>${formatDate(session.acctstarttime)}</td>
                        <td>${session.duration_formatted}</td>
                        <td>${session.download_formatted}</td>
                        <td>${session.upload_formatted}</td>
                        <td>${session.nasipaddress || '-'}</td>
                    </tr>
                `).join('');
            }
        }
    } catch (error) {
        showError('Error al cargar sesiones: ' + error.message);
    }
}

/**
 * Webhooks
 */
function initWebhooks() {
    const btnAdd = document.getElementById('btn-add-webhook');
    if (btnAdd) {
        btnAdd.addEventListener('click', () => addWebhook());
    }
}

async function loadWebhooks() {
    try {
        const response = await fetch(`${API_URL}/webhooks`);
        const result = await response.json();

        if (result.success) {
            displayWebhooks(result.data);
        }
    } catch (error) {
        showError('Error al cargar webhooks: ' + error.message);
    }
}

function displayWebhooks(webhooks) {
    const container = document.getElementById('webhooks-list');

    if (webhooks.length === 0) {
        container.innerHTML = '<p class="text-center">No hay webhooks configurados</p>';
        return;
    }

    container.innerHTML = webhooks.map(webhook => `
        <div class="webhook-item">
            <div class="webhook-info">
                <div class="webhook-url">${escapeHtml(webhook.url)}</div>
                <div class="webhook-event">Evento: ${escapeHtml(webhook.event)}</div>
            </div>
            <button class="btn btn-danger" onclick="removeWebhook('${webhook.id}')">
                Eliminar
            </button>
        </div>
    `).join('');
}

function addWebhook() {
    const url = prompt('URL del webhook:');
    if (!url) return;

    const event = prompt('Evento (user.created, user.updated, user.deleted, o * para todos):');
    if (!event) return;

    fetch(`${API_URL}/webhooks`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ url, event })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showSuccess('Webhook agregado correctamente');
            loadWebhooks();
        } else {
            showError('Error: ' + result.error);
        }
    })
    .catch(error => showError('Error: ' + error.message));
}

function removeWebhook(id) {
    if (!confirm('¿Eliminar este webhook?')) return;

    fetch(`${API_URL}/webhooks?id=${id}`, { method: 'DELETE' })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showSuccess('Webhook eliminado');
            loadWebhooks();
        } else {
            showError('Error: ' + result.error);
        }
    })
    .catch(error => showError('Error: ' + error.message));
}

/**
 * Test de Conexión
 */
function initTestConnection() {
    const btn = document.getElementById('btn-test-connection');
    if (btn) {
        btn.addEventListener('click', () => testConnection());
    }
}

async function testConnection() {
    try {
        const response = await fetch(`${API_URL}/test-connection`);
        const result = await response.json();

        if (result.success && result.data.success) {
            showSuccess('Conexión exitosa al servidor remoto');
        } else {
            showError('Error de conexión: ' + (result.data.message || result.error));
        }
    } catch (error) {
        showError('Error: ' + error.message);
    }
}

/**
 * Utilidades
 */
function generatePassword(length = 12) {
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let password = '';
    for (let i = 0; i < length; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return password;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('es-ES', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

function showSuccess(message) {
    showNotification(message, 'success');
}

function showError(message) {
    showNotification(message, 'error');
}

function showNotification(message, type = 'info') {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;
    alert.style.position = 'fixed';
    alert.style.top = '20px';
    alert.style.right = '20px';
    alert.style.zIndex = '10000';
    alert.style.minWidth = '300px';
    alert.style.animation = 'slideUp 0.3s';

    document.body.appendChild(alert);

    setTimeout(() => {
        alert.style.animation = 'fadeOut 0.3s';
        setTimeout(() => alert.remove(), 300);
    }, 3000);
}
