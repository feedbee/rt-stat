if (typeof(RtStat) == "undefined") {
    RtStat = {};
}
RtStat.WebSocketClient = function (config) {
    var webSocket;
    var connected = false;
    var protocol;

    var responseCommands = ['welcome', 'error', 'push', 'version'];

    if (!config) {
        config = {};
    }

    this.connect = function (uri) {
        protocol = new RtStat.Protocol();

        if (!uri) {
            uri = "ws://localhost:8000/";
        }

        webSocket = new WebSocket(uri);
        webSocket.onmessage = function (event) {
            var message = event.data.trim();
            console.log("Message received: " + message);

            if (config.onMessageCallback) {
                config.onMessageCallback.call(this, message);
            }

            if (config.onCmdCallback) {
                var messageParsed = protocol.parseMessage(message);
                if (responseCommands.indexOf(messageParsed.command) != -1) {
                    config.onCmdCallback(messageParsed.command, messageParsed.arguments);
                }
            }
        };
        webSocket.onclose = function () {
            console.log("Socket closed");
            connected = false;
            if (config.onCloseCallback) {
                config.onCloseCallback.call(this);
            }
        };
        webSocket.onopen = function () {
            console.log("Connected...");
            connected = true;
            if (config.onOpenCallback) {
                config.onOpenCallback.call(this);
            }
        };
        webSocket.onerror = function (event) {
            console.log("Error: " + event.data);
            if (config.onErrorCallback) {
                config.onErrorCallback.call(this, event.data);
            }
        };
    };

    this.disconnect = function () {
        webSocket.close();
        console.log("Disconnected...");
    };

    var sendCommand = function () {
        var command = arguments.length > 0 ? arguments[0].toLowerCase() : '';
        var args = arguments.length > 1 ? arguments[1] : [];

        var message = protocol.createMessage(command, args);

        webSocket.send(message);
        console.log("Message sent: " + message);
    };

    this.authenticate = function (token) {
        sendCommand("auth", [token]);
    };

    this.start = function () {
        sendCommand("start");
    };

    this.stop = function () {
        sendCommand("stop");
    };

    this.setInterval = function (interval) {
        sendCommand("interval", [interval]);
    };

    this.requestVersion = function () {
        sendCommand("version");
    };

    this.isConnected = function () {
        return connected;
    };
};

RtStat.Protocol = function () {
    this.parseMessage = function (message) {
        var dataParts = message.split('::', 2);
        var command = dataParts[0].toLowerCase();
        var args = [];
        if (dataParts.length > 1) {
            args = parseArguments(dataParts[1]);
        }

        return {
            command: command,
            arguments: args
        };
    };

    this.createMessage = function (command, args) {
        var argsEscaped = args.map(function (value) {
            return String(value).replace(/(::|\\)/g, '\\$1').replace(/\n/g, '\\n');
        });

        return command + '::' + argsEscaped.join('::');
    };

    var parseArguments = function (argsStr) {
        if (argsStr.length < 1) {
            return [];
        }

        var escapeMode = false;
        var argsStack = [''];
        for (var i = 0; i < argsStr.length; i++) {
            var char = argsStr.substr(i, 1);

            if (escapeMode) {
                escapeMode = false;
                if (char == 'n') {
                    argsStack[argsStack.length - 1] += '\n';
                } else {
                    argsStack[argsStack.length - 1] += char;
                }
            } else {
                if (char == '\\') {
                    escapeMode = true;
                } else if (char == ':' && i < argsStr.length - 1 && argsStr.substr(i + 1, 1) == ':') {
                    argsStack.push('');
                    i++;
                } else {
                    argsStack[argsStack.length - 1] += char;
                }
            }
        }

        return argsStack;
    };
};