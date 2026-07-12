/* earnings report */
var options = {
    series: [
      {
        type: "area",
        name: "کارکنان",
        data: [
          {
            x: "فروردین",
            y: 100,
          },
          {
            x: "اردیبهشت",
            y: 210,
          },
          {
            x: "خرداد",
            y: 180,
          },
          {
            x: "تیر",
            y: 454,
          },
          {
            x: "مرداد",
            y: 230,
          },
          {
            x: "شهریور",
            y: 320,
          },
          
        ],
      },
      {
        type: "area",
        name: "دانش اموزان",
        data: [
          {
            x: "فروردین",
            y: 180,
          },
          {
            x: "اردیبهشت",
            y: 620,
          },
          {
            x: "خرداد",
            y: 476,
          },
          {
            x: "تیر",
            y: 220,
          },
          {
            x: "مرداد",
            y: 520,
          },
          {
            x: "شهریور",
            y: 780,
          },
          
        ],
      },
      {
        type: "column",
        name: "معلمان",
        chart: {
          dropShadow: {
            enabled: true,
            enabledOnSeries: undefined,
            top: 5,
            left: 0,
            blur: 3,
            color: "#000",
            opacity: 0.1,
          },
        },
        data: [
          {
            x: "فروردین",
            y: 210,
          },
          {
            x: "اردیبهشت",
            y: 380,
          },
          {
            x: "خرداد",
            y: 400,
          },
          {
            x: "تیر",
            y: 250,
          },
          {
            x: "مرداد",
            y: 300,
          },
          {
            x: "شهریور",
            y: 420,
          },
          
        ],
      },
    ],
    chart: {
      height: 336,
      animations: {
        speed: 500,
      },
      toolbar: {
        show: false,
      },
    },
    colors: ["rgb(158, 92, 247)", "rgb(255, 93, 159)", "var(--primary-color)"],
    dataLabels: {
      enabled: false,
    },
    grid: {
      borderColor: "#f1f1f1",
      strokeDashArray: 3,
    },
  
    fill: {
      type: ["gradient", "gradient", "solid"],
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.7,
        opacityTo: 0.1,
        colorStops: [
          [
            {
              offset: 0,
              color: "rgb(158, 92, 247)",
              opacity: 0.05,
            },
            {
              offset: 100,
              color: "rgb(158, 92, 247)",
              opacity: 0.05,
            },
          ],[
              {
                offset: 0,
                color: "rgb(255, 93, 159)",
                opacity:0.05,
              },
              {
                offset: 100,
                color: "rgb(255, 93, 159)",
                opacity: 0.05,
              },
            ],
        ],
      },
    },
    stroke: {
      curve: "smooth",
      width: [1, 1, 0],
      dashArray: [4, 0, 0, 0],
    },
    xaxis: {
      axisTicks: {
        show: false,
      },
    },
    yaxis: {
      labels: {
        formatter: function (value) {
          return value;
        },
      },
    },
    plotOptions: {
      bar: {
        columnWidth: "15%",
        borderRadius: "2",
      },
    },
    legend: {
      show: false,
      position:"bottom",
      customLegendItems: ["کارکنان", "دانش‌آموزان", "معلمان"],
      inverseOrder: true,
    },
    markers: {
      hover: {
        sizeOffset: 5,
      },
    },
  };
  document.getElementById("attendance").innerHTML = "";
  var chart = new ApexCharts(document.querySelector("#attendance"), options);
  chart.render();
  /* earnings report */
  
  /* For Inline Calendar */
  flatpickr("#Eventscalendar", {
    inline: true,
  });
  /* For Inline Calendar */
  
  /* Students statistics */
  var options = {
    series: [{
        name: 'پروژه ها',
        type: 'line',
        data: [26, 58,44, 42, 57, 55, 45],
    }, {
        name: 'وظایف',
        type: 'line',
        data: [56, 41, 55, 34, 54, 42, 57],
    }],
    chart: {
        height: 282,
        type: 'line',
        stacked: false,
        toolbar: {
            show: false
        },
        dropShadow: {
            enabled: true,
            enabledOnSeries: undefined,
            top: 7,
            left: 0,
            blur: 3,
            color: ["var(--primary-color)", "rgb(215, 124, 247)", "rgb(12, 215, 177)"],
            opacity: 0.1
        },
    },
    colors: ["var(--primary-color)", "rgb(215, 124, 247)", "rgb(12, 215, 177)"],
    grid: {
        borderColor: '#f1f1f1',
        strokeDashArray: 3
    },
    stroke: {
        width: [2, 2, 2],
        curve: 'smooth',
    },
    plotOptions: {
        bar: {
            columnWidth: '30%',
            borderRadius: 5,
        }
    },
    labels:  ['شنبه', 'یکشنبه', 'دوشنبه', 'سه شنبه', 'چهارشنبه', 'پنج شنبه', 'جمعه'],
    markers: {
        size: 0,
    },
    legend: {
        show: true,
        position: 'top',
        fontFamily: "Montserrat",
        markers: {
            width: 10,
            height: 10,
        }
    },
    xaxis: {
        type: 'week',
        axisBorder: {
            show: true,
            color: 'rgba(119, 119, 142, 0.05)',
            offsetX: 0,
            offsetY: 0,
        },
        axisTicks: {
            show: true,
            borderType: 'solid',
            color: 'rgba(119, 119, 142, 0.05)',
            width: 6,
            offsetX: 0,
            offsetY: 0
        },
        labels: {
            rotate: -90
        }
    },
    yaxis: {
        title: {
            style: {
                color: '#adb5be',
                fontSize: '14px',
                fontFamily: 'Mulish, sans-serif',
                fontWeight: 600,
                cssClass: 'apexcharts-yaxis-label',
            },
        },
    },
    tooltip: {
        shared: true,
        theme: "dark",
    }
};
  document.getElementById("students-applicants").innerHTML = "";
  var chart = new ApexCharts(
    document.querySelector("#students-applicants"),
    options
  );
  chart.render();
  /* Students statistics */