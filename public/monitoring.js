RtStat = {};
RtStat.Monitoring = function (config) {
    //private methods
    var createCol = function (index) {
        var source   = $("#col-template").html();
        var template = Handlebars.compile(source);
        var block = $(template({colIndex: index}));

        var wrapper = $('#wrapper');
        wrapper.append(block);

        return block;
    };

    var createServer = function (id, name, container) {
        var source   = $("#server-template").html();
        var template = Handlebars.compile(source);
        var block = $(template({serverId: id, serverName: name}));

        var wrapper = $(container);
        wrapper.append(block);

        return block;
    };

    var init = function (serverId) {
        var srvPref = function (id) {
            return "server-" + serverId + "-" + id;
        };
        var srvPref$ = function (id) {
            return "#" + srvPref(id);
        };

        var types = [
            {key: 'user', color: "rgb(60,163,23)"},
            {key: 'system', color: "rgb(247,187,22)"},
            {key: 'iowait', color: "rgb(232,23,23)"}
        ];
        var cpuStat = function (allCpus) {
            for (var key in allCpus) {
                if (!allCpus.hasOwnProperty(key)) {
                    continue;
                }

                var data = allCpus[key];

                var processorBlock = $(srvPref$('processors-' + key));
                if (processorBlock.length < 1) {
                    var newEl = $(srvPref$('processors')).append($('<div class="flex">'
                        + '<span id="' + srvPref('processors-' + key + '-label') + '" class="label">' + key + '</span>'
                        + '<canvas id="' + srvPref('processors-' + key) + '" width="300" height="20"></canvas>'
                        + '<span id="' + srvPref('processors-' + key + '-value') + '" class="value"></span></div>'));
                    processorBlock = newEl.find(srvPref$('processors-' + key));
                }

                var canvas = processorBlock.get(0);
                var labelField = $(srvPref$('processors-' + key + '-label'));
                var valueField = $(srvPref$('processors-' + key + '-value'));
                labelField.text(key);
                var ctx = canvas.getContext("2d");

                ctx.clearRect(0, 0, canvas.width, canvas.height);

                var lastX = 0;
                var width = canvas.width;
                var height = canvas.height;
                types.forEach(function (element) {
                    var value = data[element.key];
                    ctx.fillStyle = element.color;
                    var segmentWidth = Math.round(value * width);
                    if (segmentWidth > 1) {
                        ctx.fillRect(lastX + 1, 1, segmentWidth - 1, canvas.height - 2);
                        lastX += segmentWidth;
                    }

                });

                valueField.text(Math.round(data.usage * 1000) / 10 + '%');
                if (data.usage >= 0.90) {
                    valueField.addClass('warning');
                }
                else {
                    valueField.removeClass('warning');
                }
            }
        };

        var typesMemory = [
            {key: 'apps', color: "rgb(33,145,29)"},
            {key: 'buffers', color: "rgb(160,20,0)"},
            {key: 'cached', color: "rgb(242,143,12)"},
            {key: 'swapCached', color: "rgb(232,23,23)"}
        ];
        var typesSwap = [
            {key: 'used', color: "rgb(232,23,23)"}
        ];
        var memInfo = function (meminfo) {
            for (var key in meminfo) {
                if (!meminfo.hasOwnProperty(key)) {
                    continue;
                }

                var data = meminfo[key];

                var canvas = $(srvPref$(key + '-canvas')).get(0);
                var valueField = $(srvPref$(key + '-value'));
                var ctx = canvas.getContext("2d");

                ctx.clearRect(0, 0, canvas.width, canvas.height);

                var lastX = 0;
                var width = canvas.width;
                var types = (key == 'memory' ? typesMemory : typesSwap);
                types.forEach(function (element) {
                    var value = data[element.key];
                    ctx.fillStyle = element.color;
                    var segmentWidth = Math.round(value / data.total * width);
                    if (segmentWidth > 1) {
                        ctx.fillRect(lastX + 1, 1, segmentWidth - 1, canvas.height - 2);
                        lastX += segmentWidth;
                    }
                });

                valueField.text(Math.round(data.used / data.total * 1000) / 10 + '%');
                if (data.usage > 0.85) {
                    valueField.addClass('warning');
                }
                else {
                    valueField.removeClass('warning');
                }
            }
        };

        var uptime = function (uptime) {
            for (var key in uptime) {
                if (!uptime.hasOwnProperty(key)) {
                    continue;
                }
                $(srvPref$('uptime-' + key)).text(uptime[key]);
            }
        };

        var processes = function (processes) {
            for (var key in processes) {
                if (!processes.hasOwnProperty(key)) {
                    continue;
                }
                $(srvPref$('processes-' + key)).text(processes[key]);
            }
        };

        var client = new AgentServerClient(function (msg) {
            var jsonResponce = JSON.parse(msg);
            cpuStat(jsonResponce.cpu_stat);
            memInfo(jsonResponce.meminfo);
            uptime(jsonResponce.uptime);
            processes(jsonResponce.processes);
        });

        var serverAddress = $(srvPref$('host')).val();
        if (serverAddress.length < 1) {
            serverAddress = 'localhost';
        }
        if (serverAddress.indexOf(':') < 0) {
            serverAddress += ":8000";
        }

        var setIntervalCallback = function () {
            client.setInterval($(srvPref$('interval')).val());
        };

        var statusBlock = $(srvPref$('status'));
        var wantToBeConnected = false;
        var connect = function () {
            statusBlock.text('Connecting...');
            client.connect({
                uri: "ws://" + serverAddress + "/",
                onOpenCallback: function () {
                    $(srvPref$('status')).text('Connected');
                    setIntervalCallback();
                    client.start();
                },
                onErrorCallback: function (data) {
//                alert('WebSockets error: ' + data);
                },
                onCloseCallback: function () {
                    if (wantToBeConnected) {
                        statusBlock.text('Disconnected. Trying to connect...');
                        setTimeout(connect, 1000);
                    } else {
                        statusBlock.text('Disconnected');
                    }
                }
            });
        };
        $(srvPref$('start-btn')).on('click', function () {
            wantToBeConnected = true;
            connect();
        });
        $(srvPref$('stop-btn')).on('click', function () {
            wantToBeConnected = false;
            if (client.isConnected()) {
                client.stop();
            }
            client.disconnect();
        });
        $(srvPref$('interval-btn')).on('click', setIntervalCallback);
    };

    // constructor
    var cols = [];
    for (var i = 0; i < config.cols; i++) {
        cols[i] = createCol(i);
    }

    for (i = 0; i < config.servers.length; i++) {
        var serverConfig = config.servers[i];
        createServer(serverConfig.id, serverConfig.name, cols[serverConfig.col - 1]);
        init(serverConfig.id);
    }
};

RtStat.Monitoring.init = function (config) {
    new RtStat.Monitoring(config);
};