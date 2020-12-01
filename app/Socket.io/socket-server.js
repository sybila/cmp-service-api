#!/usr/bin/env nodejs
/**
 * Created by satanio on 27/11/2020.
 */
 
 global.Buffer = global.Buffer || require('buffer').Buffer;
 global.navigator = {appName: 'nodejs'}; // fake the navigator object
 global.window = {}; // fake the window object

var app = require('http').createServer(handler)
var io = require('socket.io')(app);
var fs = require('fs');

var phpSettings = fs.readFileSync('../settings.local.php', 'utf8');
let regex = /'not_psw' => (.*)/g;
let res = regex.exec(phpSettings);
var psw = res[1];

app.listen(9001);

function handler (req, res) {
    res.writeHead(200, {
        "Access-Control-Allow-Methods": "GET,POST",
        "Access-Control-Allow-Credentials": true
    });
    res.end();
}

var activeUsers = [];

io.on('connection', function (socket) {
    //console.log("New Connection with transport", socket.conn.transport.name);
    //console.log('With handshake', socket.handshake);
    //console.log('With query', socket.handshake.query);
    let id = -1;
    try {
        let authHeader = socket.handshake.query.token
        id = checkKey(authHeader);
        if (id < 0) {
            throw new Error('Not authorized.');
        } else {
            activeUsers[socket.id] = id;
            console.log("We have a new client: " + socket.id);
            console.log(activeUsers);
        }
    } catch (e) {
        console.log(e.message);
        socket.disconnect;
    }

    socket.on('notification',
        function(data) {
            payload = JSON.parse(data);
            sendNotificationTo(id, payload.ids,payload.data, socket);
        }
    );

    socket.on('disconnect', function() {
        delete activeUsers[socket.id];
        console.log("Client has disconnected");
    });
});

function checkKey(key) {
    let decode = Buffer.from(key, 'base64').toString();
    let credentials = decode.split(':',2);
    if ((credentials[1]) === psw) {
        return Number.isInteger(parseInt(credentials[0])) ? credentials[0] : -1;
    }
    else {
        return -1;
    }
}

function sendNotificationTo(from, toIds, jsonData, socket)
{
    toIds.forEach(id => {
        for (var to in activeUsers) {
            if (id == activeUsers[to]) {
                if (to !== -1 && (to !== from)) {
                    console.log(id);
                    console.log(jsonData);
                    socket.broadcast.to(to).emit('notification', jsonData);
                }
            }
        }
    })
}
