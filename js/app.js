let ws;
let token = localStorage.getItem('token');
let currentUser = null;

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
                loadInitialData();
            } else {
                alert('Authentication failed');
                window.location.href = 'index.html';
            }
            break;
        case 'data':
            if (msg.module === 'client') {
                renderClients(msg.data);
                updateClientCount(msg.data.length);
            } else if (msg.module === 'appointment') {
                renderAppointments(msg.data);
                updateAppointmentCount(msg.data.length);
                updateChart(msg.data);
                populateClientSelect(msg.data);
            }
            break;
        case 'cud':
            addActivity(`${msg.action} ${msg.module} ${msg.data ? msg.data.id : msg.id}`);
            requestData('client');
            requestData('appointment');
            break;
        case 'ack':
            if (!msg.success) {
                alert('Error: ' + msg.error);
            }
            break;
    }
}

function requestData(module) {
    ws.send(JSON.stringify({ action: 'get', module: module }));
}

function loadInitialData() {
    requestData('client');
    requestData('appointment');
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

function renderClients(clients) {
    const tbody = document.querySelector('#clientsTable tbody');
    tbody.innerHTML = '';
    clients.forEach(c => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${c.id}</td>
            <td>${c.name}</td>
            <td>${c.email}</td>
            <td>${c.phone}</td>
            <td>
                <button onclick="editClient(${c.id})">Edit</button>
                <button onclick="deleteClient(${c.id})">Delete</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function renderAppointments(appointments) {
    const tbody = document.querySelector('#appointmentsTable tbody');
    tbody.innerHTML = '';
    appointments.forEach(a => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${a.id}</td>
            <td>${a.client_name}</td>
            <td>${a.service}</td>
            <td>${a.appointment_date}</td>
            <td>${a.appointment_time}</td>
            <td>${a.status}</td>
            <td>
                <button onclick="editAppointment(${a.id})">Edit</button>
                <button onclick="deleteAppointment(${a.id})">Delete</button>
            </td>
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

function createClient(data) {
    ws.send(JSON.stringify({ action: 'create', module: 'client', data: data }));
}

function updateClient(id, data) {
    ws.send(JSON.stringify({ action: 'update', module: 'client', id: id, data: data }));
}

function deleteClient(id) {
    if (confirm('Delete client?')) {
        ws.send(JSON.stringify({ action: 'delete', module: 'client', id: id }));
    }
}

function createAppointment(data) {
    ws.send(JSON.stringify({ action: 'create', module: 'appointment', data: data }));
}

function updateAppointment(id, data) {
    ws.send(JSON.stringify({ action: 'update', module: 'appointment', id: id, data: data }));
}

function deleteAppointment(id) {
    if (confirm('Delete appointment?')) {
        ws.send(JSON.stringify({ action: 'delete', module: 'appointment', id: id }));
    }
}

window.editClient = (id) => {
    fetch(`php/get_client.php?id=${id}`)
        .then(res => res.json())
        .then(client => {
            document.getElementById('clientId').value = client.id;
            document.getElementById('clientName').value = client.name;
            document.getElementById('clientEmail').value = client.email;
            document.getElementById('clientPhone').value = client.phone;
            document.getElementById('clientModal').style.display = 'block';
        });
};

window.editAppointment = (id) => {
    fetch(`php/get_appointment.php?id=${id}`)
        .then(res => res.json())
        .then(app => {
            document.getElementById('appointmentId').value = app.id;
            document.getElementById('appointmentClientId').value = app.client_id;
            document.getElementById('appointmentService').value = app.service;
            document.getElementById('appointmentDate').value = app.appointment_date;
            document.getElementById('appointmentTime').value = app.appointment_time;
            document.getElementById('appointmentStatus').value = app.status;
            document.getElementById('appointmentModal').style.display = 'block';
        });
};

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

document.getElementById('clientForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const id = document.getElementById('clientId').value;
    const data = {
        name: document.getElementById('clientName').value,
        email: document.getElementById('clientEmail').value,
        phone: document.getElementById('clientPhone').value
    };
    if (id) {
        updateClient(id, data);
    } else {
        createClient(data);
    }
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
    if (id) {
        updateAppointment(id, data);
    } else {
        createAppointment(data);
    }
    document.getElementById('appointmentModal').style.display = 'none';
});

document.querySelectorAll('.modal .close').forEach(btn => {
    btn.addEventListener('click', () => {
        btn.closest('.modal').style.display = 'none';
    });
});

window.onclick = (event) => {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
};

connectWebSocket();