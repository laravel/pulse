import Chart from 'chart.js/auto';

const formatDate = (value) => {
    if (value.match(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/) === null) {
        throw new Error(`Unknown date format [${value}].`);
    }

    const [date, time] = value.split(' ')
    const [year, month, day] = date.split('-').map(Number)
    const [hour, minute, second] = time.split(':').map(Number)

    return new Date(
        Date.UTC(year, month - 1, day, hour, minute, second, 0)
    ).toLocaleString(undefined, {
        year: "2-digit",
        day: "2-digit",
        month: "2-digit",
        hourCycle: "h24",
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
        timeZoneName: "short"
    });
}

window.Chart = Chart;
window.formatDate = formatDate
