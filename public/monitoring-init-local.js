RtStat.Monitoring.init({
    columns: [
        {
            servers: [
                {
                    id: 'a',
                    name: 'Server A',
                    host: 'localhost',
                    interval: 1.5,
                    autoStart: true
                }
            ]
        },
        {
            servers: [
                {
                    id: 'b',
                    name: 'Server B',
                    host: 'localhost',
                    interval: 1.5,
                    autoStart: true
                }
            ]
        }
    ]
});