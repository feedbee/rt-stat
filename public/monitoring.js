if (typeof(RtStat) == "undefined") {
    RtStat = {};
}
RtStat.Monitoring = function (config) {
    var columns = [];

    // constructor
    for (var i = 0; i < config.columns.length; i++) {
        columns[i] = new RtStat.Monitoring.Column(i);
        $('#wrapper').append(columns[i].getBlock());

        for (var j = 0; j < config.columns[i].servers.length; j++) {
            var serverConfig = config.columns[i].servers[j];
            var server = new RtStat.Monitoring.Server(serverConfig);
            columns[i].addServer(server);
        }
    }

    this.getCurrentConfig = function () {
        var currentConfig = {columns: []};
        for (var i in columns) {
            if (!columns.hasOwnProperty(i)) {
                continue;
            }
            currentConfig.columns.push({
                servers: columns[i].getServers()
            });
        }

        return currentConfig;
    };
};

RtStat.Monitoring.init = function (config) {
    var e = new RtStat.Monitoring(config);
};

RtStat.Monitoring.Column = function (index) {
    var block = (function () {
        var source   = $("#col-template").html();
        var template = Handlebars.compile(source);
        return $(template({colIndex: index}));
    })();

    var servers = [];

    this.getBlock = function () {
        return block;
    };

    this.remove = function () {
        block.remove();
    };

    this.addServer = function (server) {
        block.append(server.getBlock());
        servers.push(server);
        server.init();
    };

    this.removeServer = function (server) {
        server.uninit();
        block.remove(server.getBlock());
        servers.splice(servers.indexOf(server));
    };

    this.getServers = function () {
        return servers.slice(0);
    };
};

RtStat.Monitoring.Server = function (initialConfig) {
    var self = this;

    var block = (function () {
        var source   = $("#server-template").html();
        var template = Handlebars.compile(source);
        return block = $(template({server: initialConfig}));
    })();
    if (initialConfig.autoStart) {
        block.find('.auto-start').prop('checked', true);
    }

    this.getBlock = function () {
        return block;
    };

    var srvPref = function (id) {
        return "server-" + initialConfig.id + "-" + id;
    };
    var srvPref$ = function (id) {
        return "#" + srvPref(id);
    };

    var cpuStat = function (allCpus) {
        var typesCpu = [
            {key: 'user', color: "rgb(60,163,23)"},
            {key: 'system', color: "rgb(247,187,22)"},
            {key: 'iowait', color: "rgb(232,23,23)"}
        ];
        graphInfo(allCpus, typesCpu, 'processors', 'processors-cpuX-template', 'processors', 0.90, function (data) { return data.usage;},
            function (data, element) { return data[element.key];});
    };

    var memInfo = function (meminfo) {
        var typesMemory = [
            {key: 'apps', color: "rgb(33,145,29)"},
            {key: 'buffers', color: "rgb(160,20,0)"},
            {key: 'cached', color: "rgb(242,143,12)"},
            {key: 'swapCached', color: "rgb(232,23,23)"}
        ];
        graphInfo({memory: meminfo.memory}, typesMemory, '', '', '', 0.85, function (data) {return (data.apps + data.buffers) / data.total;},
            function (data, element) {return data[element.key] / data.total;});

        var typesSwap = [
            {key: 'used', color: "rgb(232,23,23)"}
        ];
        graphInfo({swap: meminfo.swap}, typesSwap, '', '', '', 0.10, function (data) {return data.used / data.total;},
            function (data, element) {return data[element.key] / data.total;});
    };

    var graphInfo = function (dataset, types, blockSelector, blockTemplateSelector, appendToSelector, warnLimit,
                        dataGetOverallUsageValueClb, dataGetElementUsageValueClb) {
        for (var key in dataset) {
            if (!dataset.hasOwnProperty(key)) {
                continue;
            }

            var data = dataset[key];

            var compiledBlockSelector = (blockSelector ? blockSelector + '-' : '') + key + '-canvas';
            var block = $(srvPref$(compiledBlockSelector));
            if (block.length < 1) {
                var template = Handlebars.compile($(srvPref$(blockTemplateSelector)).html());
                var newEl =  $(template({cpuId: key}));
                $(srvPref$(appendToSelector)).append(newEl);
                block = newEl.find(srvPref$(compiledBlockSelector));
            }

            var valueField = block.parent().find('span.value');
            if (valueField) {
                var value = dataGetOverallUsageValueClb(data);
                valueField.text(Math.round(value * 1000) / 10 + '%');
                if (value >= warnLimit) {
                    valueField.addClass('warning');
                }
                else {
                    valueField.removeClass('warning');
                }
            }

            var canvas = block.get(0);
            var ctx = canvas.getContext("2d");
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            var lastX = 0;
            var width = canvas.width;
            types.forEach(function (element) {
                var value = dataGetElementUsageValueClb(data, element);
                ctx.fillStyle = element.color;
                var segmentWidth = Math.round(value * width);
                if (segmentWidth > 1) {
                    ctx.fillRect(lastX + 1, 1, segmentWidth - 1, canvas.height - 2);
                    lastX += segmentWidth;
                }
            });
        }
    };

    var uptime = function (uptime) {
        for (var key in uptime) {
            if (!uptime.hasOwnProperty(key)) {
                continue;
            }
            $(srvPref$('uptime-' + key)).text(uptime[key]);
        }
        $(srvPref$('title')).html( '[ <small>' + uptime.la1 + '</small> ' + uptime.la5 + ' ]');
    };

    var processes = function (processes) {
        for (var key in processes) {
            if (!processes.hasOwnProperty(key)) {
                continue;
            }
            $(srvPref$('processes-' + key)).text(processes[key]);
        }
    };

    var statusBlock; // will be assigned later in init()
    var wantToBeConnected = false;
    var connecting = false;
    var client = new RtStat.WebSocketClient({
        onCmdCallback: function (cmd, args) {
            if (cmd == 'push') {
                if (args.length < 1) {
                    console.log('Error: push command without arguments');
                }
                var msg = args[0];
                var jsonResponce = JSON.parse(msg);
                cpuStat(jsonResponce.cpu_stat);
                memInfo(jsonResponce.meminfo);
                uptime(jsonResponce.uptime);
                processes(jsonResponce.processes);
            }
        },
        onOpenCallback: function () {
            connecting = false;
            statusBlock.text('Connected');
            var token = self.getToken();
            if (token) {
                client.authenticate(token);
            }
            setInterval();
            client.start();
        },
        onErrorCallback: function (data) {
            statusBlock.text('Error. Disconnecting...');
        },
        onCloseCallback: function () {
            if (wantToBeConnected) {
                statusBlock.text('Disconnected. Trying to connect...');
                setTimeout(connect, 1000);
            } else {
                statusBlock.text('Disconnected');
                connecting = false;
            }
        }
    });


    var connect = function () {
        statusBlock.text('Connecting...');

        var wsUri = self.getHost();
        var hostMatch = wsUri.match(/^((ws|wss):\/\/)?([a-zA-Z0-9\-\.]{1,63})(:(\d{1,5}))?(\/.*)?$/i);
        if (hostMatch) {
            wsUri = hostMatch[3]; // host
            if (hostMatch[2]) { // protocol
                wsUri = hostMatch[1] + wsUri;
            } else {
                wsUri = 'ws://' + wsUri;
            }
            if (hostMatch[5]) { // port
                wsUri += ":" + hostMatch[5];
            } else {
                wsUri += ":8000";
            }
            if (hostMatch[6]) { // local part
                wsUri += hostMatch[6];
            } else {
                wsUri += "/";
            }
        } else {
            wsUri = "ws://localhost:8000/";
        }

        client.connect(wsUri);
    };

    var start = function () {
        if (connecting) {
            return;
        }
        if (client.isConnected()) {
            client.stop();
        }

        wantToBeConnected = true;
        connecting = true;
        connect();
    };
    var stop = function () {
        wantToBeConnected = false;
        if (client.isConnected()) {
            client.stop();
        }
        client.disconnect();
    };
    var setInterval = function () {
        client.setInterval(self.getInterval());
    };

    this.init = function () {
        $(srvPref$('start-btn')).on('click', start);
        $(srvPref$('stop-btn')).on('click', stop);
        $(srvPref$('interval-btn')).on('click', setInterval);
        $(srvPref$('options-btn')).on('click', function () {
            $(srvPref$('options')).toggle();
            var t = $(this);
            if (t.text() == 'Options') {
                t.text('Hide options');
            } else {
                t.text('Options');
            }
        });
        $(srvPref$('name-btn')).on('click', function () {
            var newName = prompt('Enter new server name', self.getName());
            self.setName(newName);
        });

        statusBlock = $(srvPref$('status'));

        if (initialConfig.autoStart) {
            start();
        }
    };

    this.uninit = function () {
        stop();
    };

    this.getName = function () {
        return $(srvPref$('name')).text();
    };
    this.setName = function (value) {
        $(srvPref$('name')).text(value);
    };

    this.getHost = function () {
        return $(srvPref$('host')).val();
    };
    this.setHost = function (value) {
        $(srvPref$('host')).val(value);
    };

    this.getInterval = function () {
        return parseFloat($(srvPref$('interval')).val());
    };
    this.setInterval = function (value) {
        $(srvPref$('interval')).val(value);
    };

    this.getToken = function () {
        return $(srvPref$('token')).val();
    };
    this.setToken = function (value) {
        $(srvPref$('token')).val(value);
    };

    this.getAutoStart = function () {
        return $(srvPref$('auto-start')).is(':checked');
    };
    this.setAutoStart = function (value) {
        $(srvPref$('auto-start')).attr('checked', 'checked');
    };

    this.getCurrentConfig = function () {
        return {
            id: initialConfig.id,
            name: this.getName(),
            host: this.getHost(),
            interval: this.getInterval(),
            autoStart: this.getAutoStart()
        };
    };
};