if (typeof(RtStat) == "undefined") {
    RtStat = {};
}
RtStat.WebSocketClient = function (config) {
    var webSocket;
    var connected = false;

    var responseCommands = ['welcome', 'error', 'push', 'version'];

    if (!config) {
        config = {};
    }

    this.connect = function (uri) {
        if (!uri) {
            uri = "ws://localhost:8000/";
        }

        webSocket = new WebSocket(uri);
        webSocket.onmessage = function (event) {
            var data = event.data.trim();
            console.log("Message: " + data);

            if (config.onMessageCallback) {
                config.onMessageCallback.call(this, data);
            }

            if (config.onCmdCallback) {
                var dataParts = data.split('::', 2);
                var cmd = dataParts[0].toLowerCase();
                if (responseCommands.indexOf(cmd) != -1)
                {
                    if (dataParts.length > 1) {
                        var args = parseArgs(dataParts[1]);
                    }
                    config.onCmdCallback(cmd, args);
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
        var command = arguments.length > 0 ? arguments[0] : '';
        var args = arguments.length > 1 ? arguments[1] : [];

        var argsEscaped = args.map(function (value) {
            return value.replace(/(::|\\)/g, '\\$1').replace(/\n/g, '\\n');
        });
        var cmdStr = command + '::' + argsEscaped.join('::');

        webSocket.send(cmdStr);
        console.log("Command: " + cmdStr);
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

    var parseArgs = function (argsStr) {
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