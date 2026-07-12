var options = {
    series: [
        {
            name: 'سال گذشته',
            type: 'line',
            data: [47, 43, 55, 55, 41, 41, 53, 42, 47]
            // 35, 60, 41, 57, 52, 63, 41, 41, 65, 65, 53, 57
        },
        {
            name: 'سال جاری',
            type: 'area',
            data: [35, 60, 41, 57, 52, 63, 41, 41, 65]
        },
    ],
    chart: {
        toolbar: {
            show: false
        },
        type: 'line',
        height: 300,
        dropShadow: {
            enabled: true,
            enabledOnSeries: undefined,
            top: 7,
            left: 0,
            blur: 1,
            color: ["var(--primary-color)",  'rgb(227, 84, 212)'],
            opacity: 0.05,
          },
    },
    grid: {
        borderColor: '#f1f1f1',
        strokeDashArray: 3
    },
    labels: ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر'],
    dataLabels: {
        enabled: false
    },
    stroke: {
        width: [1.5, 2],
        curve: ['smooth', 'smooth'],
        dashArray: [0, 5],
    },
    fill: {
        type: ['soild', 'gradient'],
        gradient:{
            opacityFrom: 0.23,
            opacityTo: 0.23,
            shadeIntensity: 0.3,
        },
    },
    legend: {
        show: false,
        position: 'top',
    },
    xaxis: {
        axisBorder: {
            color: '#e9e9e9',
        },
    },
    plotOptions: {
        bar: {
            columnWidth: "20%",
            borderRadius: 2
        }
    },
    colors: ["var(--primary-color)", "rgb(227, 84, 212)"],
};
document.querySelector("#nft-statistics").innerHTML = ""
var chart2 = new ApexCharts(document.querySelector("#nft-statistics"), options);
chart2.render();