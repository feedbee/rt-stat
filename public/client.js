if (typeof(RtStat) == "undefined") {
    RtStat = {};
}
RtStat.WebSocketClient = function (onCmdCallback) {
    var webSocket;
    var connected = false;

    var responseCommands = ['welcome', 'error', 'push', 'version'];

    this.connect = function (config) {
        if (!config) {
            config = {};
        }
        if (!config.uri) {
            config.uri = "ws://localhost:8000/";
        }

        webSocket = new WebSocket(config.uri);
        webSocket.onmessage = function (event) {
            var data = event.data.trim();
            console.log("Message: " + data);

            if (config.onMessageCallback) {
                config.onMessageCallback.call(this, data);
            }

            if (onCmdCallback) {
                var dataParts = data.split('::');
                var cmd = dataParts[0].toLowerCase();
                if (responseCommands.indexOf(dataParts[0].toLowerCase()) != -1)
                {
                    var args = [];
                    for (var i = 1; i < dataParts.length; i++) {
                        var part = dataParts[i];
                        args.push(part);
                        while (part.length > 0 && part.substr(part.length - 1) == '\\'
                            && (part.substr(part.length - 2, 1) != '\\' || part.length < 2))
                        {
                            args.push('::' + (i + 1 < dataParts.length ? dataParts[i + 1] : ''));
                        }
                    }
                    onCmdCallback(cmd, args);
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
            return value.replace(/::/g, '\\::');
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
    }
};