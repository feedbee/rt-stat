RtStat.Monitoring.init({
    cols: 2,
    servers: [
        {
            id: 'a',
            name: 'Server A',
            host: 'localhost',
            interval: 1.5,
            col: 1
        },
        {
            id: 'b',
            name: 'Server B',
            host: 'localhost',
            interval: 1.5,
            col: 2
        }
    ]
});