let ws;
let token = localStorage.getItem('token');
let currentUserRole = null;
let currentPages = { client: 1, appointment: 1, service: 1, public_booking: 1 };

function connectWebSocket() {
    ws = new WebSocket('ws://localhost:8080');

    ws.onopen = () => {
        ws.send(JSON.stringify({ action: 'authenticate', token: token }));
    };

    ws.onmessage = (event) => {
        const msg = JSON.parse(event.data);
        handleMessage(msg);
    };

    ws.onclose = () => {
        setTimeout(connectWebSocket, 3000);
    };
}

function handleMessage(msg) {
    switch (msg.event) {
        case 'authenticated':
            if (msg.success) {
                currentUserRole = msg.role;
                document.getElementById('role').textContent = currentUserRole;
                document.getElementById('username').textContent = 'User'; // fetch from session if needed
                loadInitialData();
            } else {
                alert('Authentication failed');
                window.location.href = 'index.html';
            }
            break;
        case 'data':
            if (msg.module === 'client') {
                renderClients(msg.data);
                updateClientCount(msg.total);
                updatePagination('client', msg.page, msg.total, msg.limit);
            } else if (msg.module === 'appointment') {
                renderAppointments(msg.data);
                updateAppointmentCount(msg.total);
                updatePagination('appointment', msg.page, msg.total, msg.limit);
                updateChart(msg.data);
                populateClientSelect(msg.data);
            } else if (msg.module === 'service') {
                renderServices(msg.data);
                updateServiceCount(msg.total);
                updatePagination('service', msg.page, msg.total, msg.limit);
            } else if (msg.module === 'public_booking') {
                renderBookings(msg.data);
                updateBookingCount(msg.total);
                updatePagination('public_booking', msg.page, msg.total, msg.limit);
            }
            break;
        case 'cud':
            addActivity(`${msg.action} ${msg.module} ${msg.data ? msg.data.id : msg.id}`);
            // Refresh all modules
            requestData('client', currentPages.client);
            requestData('appointment', currentPages.appointment);
            requestData('service', currentPages.service);
            requestData('public_booking', currentPages.public_booking);
            break;
        case 'ack':
            if (!msg.success) {
                alert('Error: ' + msg.error);
            }
            break;
        case 'search_results':
            displaySearchResults(msg.data);
            break;
        case 'audit_logs':
            displayAuditLogs(msg.data);
            break;
    }
}

function requestData(module, page = 1) {
    ws.send(JSON.stringify({ action: 'get', module: module, page: page, limit: 10 }));
}

function loadInitialData() {
    requestData('client', 1);
    requestData('appointment', 1);
    requestData('service', 1);
    requestData('public_booking', 1);
}

function addActivity(text) {
    const feed = document.getElementById('activityFeed');
    const li = document.createElement('li');
    li.textContent = new Date().toLocaleTimeString() + ': ' + text;
    feed.prepend(li);
    if (feed.children.length > 10) feed.removeChild(feed.lastChild);
}

function updateClientCount(count) {
    document.getElementById('clientCount').textContent = count;
}
function updateAppointmentCount(count) {
    document.getElementById('appointmentCount').textContent = count;
}
function updateServiceCount(count) {
    document.getElementById('serviceCount').textContent = count;
}
function updateBookingCount(count) {
    document.getElementById('bookingCount').textContent = count;
}

function renderClients(clients) {
    const tbody = document.querySelector('#clientsTable tbody');
    tbody.innerHTML = '';
    clients.forEach(c => {
        const deleteBtn = currentUserRole === 'admin' ? `<button onclick="deleteClient(${c.id})">Delete</button>` : '';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${c.id}</td><td>${escapeHtml(c.name)}</td><td>${escapeHtml(c.email)}</td><td>${escapeHtml(c.phone)}</td>
            <td><button onclick="editClient(${c.id})">Edit</button> ${deleteBtn}</td>
        `;
        tbody.appendChild(tr);
    });
}

function renderAppointments(appointments) {
    const tbody = document.querySelector('#appointmentsTable tbody');
    tbody.innerHTML = '';
    appointments.forEach(a => {
        const deleteBtn = currentUserRole === 'admin' ? `<button onclick="deleteAppointment(${a.id})">Delete</button>` : '';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${a.id}</td><td>${escapeHtml(a.client_name)}</td><td>${escapeHtml(a.service)}</td>
            <td>${a.appointment_date}</td><td>${a.appointment_time}</td><td>${a.status}</td>
            <td><button onclick="editAppointment(${a.id})">Edit</button> ${deleteBtn}</td>
        `;
        tbody.appendChild(tr);
    });
}

function renderServices(services) {
    const tbody = document.querySelector('#servicesTable tbody');
    tbody.innerHTML = '';
    services.forEach(s => {
        const deleteBtn = currentUserRole === 'admin' ? `<button onclick="deleteService(${s.id})">Delete</button>` : '';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${s.id}</td><td>${escapeHtml(s.name)}</td><td>${s.duration}</td><td>$${parseFloat(s.price).toFixed(2)}</td>
            <td><button onclick="editService(${s.id})">Edit</button> ${deleteBtn}</td>
        `;
        tbody.appendChild(tr);
    });
}

function renderBookings(bookings) {
    const tbody = document.querySelector('#bookingsTable tbody');
    tbody.innerHTML = '';
    bookings.forEach(b => {
        const deleteBtn = currentUserRole === 'admin' ? `<button onclick="deleteBooking(${b.id})">Delete</button>` : '';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${b.id}</td><td>${escapeHtml(b.customer_name)}</td><td>${escapeHtml(b.customer_email)}</td>
            <td>${escapeHtml(b.service_name || 'N/A')}</td><td>${b.preferred_date}</td><td>${b.preferred_time}</td>
            <td>${b.status}</td>
            <td><button onclick="editBookingStatus(${b.id}, '${b.status}')">Update Status</button> ${deleteBtn}</td>
        `;
        tbody.appendChild(tr);
    });
}

function populateClientSelect(appointments) {
    const select = document.getElementById('appointmentClientId');
    const clients = [...new Set(appointments.map(a => a.client_id))].map(id => {
        const client = appointments.find(a => a.client_id === id);
        return { id: client.client_id, name: client.client_name };
    });
    select.innerHTML = '<option value="">Select Client</option>';
    clients.forEach(c => {
        const option = document.createElement('option');
        option.value = c.id;
        option.textContent = c.name;
        select.appendChild(option);
    });
}

// Pagination
function updatePagination(module, page, total, limit) {
    currentPages[module] = page;
    const totalPages = Math.ceil(total / limit);
    const infoSpan = document.querySelector(`.pagination[data-module="${module}"] .page-info`);
    if (infoSpan) infoSpan.textContent = `Page ${page} of ${totalPages}`;
}
function changePage(module, direction) {
    const newPage = currentPages[module] + direction;
    if (newPage < 1) return;
    requestData(module, newPage);
}
document.querySelectorAll('.prev-page').forEach(btn => {
    btn.addEventListener('click', () => changePage(btn.dataset.module, -1));
});
document.querySelectorAll('.next-page').forEach(btn => {
    btn.addEventListener('click', () => changePage(btn.dataset.module, 1));
});

// Global Search
document.getElementById('globalSearchBtn').addEventListener('click', () => {
    const query = document.getElementById('globalSearchInput').value;
    ws.send(JSON.stringify({ action: 'search', query: query }));
});
function displaySearchResults(results) {
    if (!results.length) {
        alert('No results found.');
        return;
    }
    let html = '<h3>Search Results</h3><ul>';
    results.forEach(r => {
        html += `<li><strong>${r.type}</strong>: `;
        if (r.type === 'client') html += `${escapeHtml(r.name)} (${escapeHtml(r.email)})`;
        else if (r.type === 'appointment') html += `${escapeHtml(r.client_name)} - ${escapeHtml(r.service)} on ${r.appointment_date}`;
        else if (r.type === 'service') html += `${escapeHtml(r.name)} - $${r.price}`;
        else if (r.type === 'public_booking') html += `${escapeHtml(r.customer_name)} (${escapeHtml(r.customer_email)}) on ${r.preferred_date}`;
        html += `</li>`;
    });
    html += '</ul>';
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'block';
    modal.innerHTML = `<div class="modal-content"><span class="close">&times;</span>${html}</div>`;
    document.body.appendChild(modal);
    modal.querySelector('.close').onclick = () => modal.remove();
}

// Audit Logs (Admin only)
document.getElementById('auditLogBtn').addEventListener('click', () => {
    if (currentUserRole !== 'admin') {
        alert('Only admin can view audit logs');
        return;
    }
    ws.send(JSON.stringify({ action: 'get_audit_logs', filters: {} }));
    document.getElementById('auditLogModal').style.display = 'block';
});
document.getElementById('applyAuditFilter').addEventListener('click', () => {
    const action = document.getElementById('auditActionFilter').value;
    ws.send(JSON.stringify({ action: 'get_audit_logs', filters: { action: action } }));
});
function displayAuditLogs(logs) {
    const tbody = document.querySelector('#auditLogTable tbody');
    tbody.innerHTML = '';
    logs.forEach(log => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${log.id}</td><td>${escapeHtml(log.username)}</td><td>${log.action}</td>
            <td>${log.module}</td><td>${log.record_id || ''}</td><td>${escapeHtml(log.details)}</td>
            <td>${log.created_at}</td>
        `;
        tbody.appendChild(tr);
    });
}
document.querySelectorAll('#auditLogModal .close').forEach(btn => {
    btn.addEventListener('click', () => document.getElementById('auditLogModal').style.display = 'none');
});

// CRUD functions
function createClient(data) { ws.send(JSON.stringify({ action: 'create', module: 'client', data: data })); }
function updateClient(id, data) { ws.send(JSON.stringify({ action: 'update', module: 'client', id: id, data: data })); }
function deleteClient(id) { if (confirm('Delete client?')) ws.send(JSON.stringify({ action: 'delete', module: 'client', id: id })); }
function createAppointment(data) { ws.send(JSON.stringify({ action: 'create', module: 'appointment', data: data })); }
function updateAppointment(id, data) { ws.send(JSON.stringify({ action: 'update', module: 'appointment', id: id, data: data })); }
function deleteAppointment(id) { if (confirm('Delete appointment?')) ws.send(JSON.stringify({ action: 'delete', module: 'appointment', id: id })); }
function createService(data) { ws.send(JSON.stringify({ action: 'create', module: 'service', data: data })); }
function updateService(id, data) { ws.send(JSON.stringify({ action: 'update', module: 'service', id: id, data: data })); }
function deleteService(id) { if (confirm('Delete service?')) ws.send(JSON.stringify({ action: 'delete', module: 'service', id: id })); }
function updateBookingStatus(id, status) { ws.send(JSON.stringify({ action: 'update', module: 'public_booking', id: id, data: { status: status } })); }
function deleteBooking(id) { if (confirm('Delete booking?')) ws.send(JSON.stringify({ action: 'delete', module: 'public_booking', id: id })); }

// Edit handlers
window.editClient = (id) => {
    fetch(`php/get_client.php?id=${id}`).then(res => res.json()).then(client => {
        document.getElementById('clientId').value = client.id;
        document.getElementById('clientName').value = client.name;
        document.getElementById('clientEmail').value = client.email;
        document.getElementById('clientPhone').value = client.phone;
        document.getElementById('clientModal').style.display = 'block';
    });
};
window.editAppointment = (id) => {
    fetch(`php/get_appointment.php?id=${id}`).then(res => res.json()).then(app => {
        document.getElementById('appointmentId').value = app.id;
        document.getElementById('appointmentClientId').value = app.client_id;
        document.getElementById('appointmentService').value = app.service;
        document.getElementById('appointmentDate').value = app.appointment_date;
        document.getElementById('appointmentTime').value = app.appointment_time;
        document.getElementById('appointmentStatus').value = app.status;
        document.getElementById('appointmentModal').style.display = 'block';
    });
};
window.editService = (id) => {
    fetch(`php/get_service.php?id=${id}`).then(res => res.json()).then(service => {
        document.getElementById('serviceId').value = service.id;
        document.getElementById('serviceName').value = service.name;
        document.getElementById('serviceDuration').value = service.duration;
        document.getElementById('servicePrice').value = service.price;
        document.getElementById('serviceModal').style.display = 'block';
    });
};
window.editBookingStatus = (id, currentStatus) => {
    document.getElementById('bookingId').value = id;
    document.getElementById('bookingStatus').value = currentStatus;
    document.getElementById('bookingStatusModal').style.display = 'block';
};

// Add button listeners
document.getElementById('addClientBtn').addEventListener('click', () => {
    document.getElementById('clientId').value = '';
    document.getElementById('clientForm').reset();
    document.getElementById('clientModal').style.display = 'block';
});
document.getElementById('addAppointmentBtn').addEventListener('click', () => {
    document.getElementById('appointmentId').value = '';
    document.getElementById('appointmentForm').reset();
    document.getElementById('appointmentModal').style.display = 'block';
});
document.getElementById('addServiceBtn').addEventListener('click', () => {
    document.getElementById('serviceId').value = '';
    document.getElementById('serviceForm').reset();
    document.getElementById('serviceModal').style.display = 'block';
});

// Form submissions
document.getElementById('clientForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const id = document.getElementById('clientId').value;
    const data = {
        name: document.getElementById('clientName').value,
        email: document.getElementById('clientEmail').value,
        phone: document.getElementById('clientPhone').value
    };
    if (id) updateClient(id, data);
    else createClient(data);
    document.getElementById('clientModal').style.display = 'none';
});
document.getElementById('appointmentForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const id = document.getElementById('appointmentId').value;
    const data = {
        client_id: document.getElementById('appointmentClientId').value,
        service: document.getElementById('appointmentService').value,
        date: document.getElementById('appointmentDate').value,
        time: document.getElementById('appointmentTime').value,
        status: document.getElementById('appointmentStatus').value
    };
    if (id) updateAppointment(id, data);
    else createAppointment(data);
    document.getElementById('appointmentModal').style.display = 'none';
});
document.getElementById('serviceForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const id = document.getElementById('serviceId').value;
    const data = {
        name: document.getElementById('serviceName').value,
        duration: document.getElementById('serviceDuration').value,
        price: document.getElementById('servicePrice').value
    };
    if (id) updateService(id, data);
    else createService(data);
    document.getElementById('serviceModal').style.display = 'none';
});
document.getElementById('bookingStatusForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const id = document.getElementById('bookingId').value;
    const status = document.getElementById('bookingStatus').value;
    updateBookingStatus(id, status);
    document.getElementById('bookingStatusModal').style.display = 'none';
});

// Close modals
document.querySelectorAll('.modal .close').forEach(btn => {
    btn.addEventListener('click', () => btn.closest('.modal').style.display = 'none');
});
window.onclick = (event) => {
    if (event.target.classList.contains('modal')) event.target.style.display = 'none';
};

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

connectWebSocket();