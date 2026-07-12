/* stocks swiper */
var swiper = new Swiper(".swiper-basic", {
  loop: true,
  slidesPerView: 1,
  spaceBetween: 20,
  autoplay: {
      delay: 1000,
      disableOnInteraction: false,
  },
  breakpoints: {
      500: {
          slidesPerView: 2,
          spaceBetween: 20,
      },
      768: {
          slidesPerView: 3,
          spaceBetween: 20,
      },
      1024: {
          slidesPerView: 3,
          spaceBetween: 20,
      },
      1200: {
          slidesPerView: 3,
          spaceBetween: 20,
      },
      1400: {
          slidesPerView: 4,
          spaceBetween: 20,
      },
      1600: {
          slidesPerView: 4,
          spaceBetween: 20,
      },
      1800: {
          slidesPerView: 5,
          spaceBetween: 20,
      },
  },
});
  /* stocks swiper */
  
  var options = {
      series: [1624, 1267, 1153],
      labels: ["سهام", "وجوه", "اوراق قرضه"],
      chart: {
        height: 288,
        type: 'donut',
      },
      dataLabels: {
        enabled: false,
      },
      legend: {
        show: false,
      },
      stroke: {
        show: true,
        curve: 'smooth',
        lineCap: 'round',
        colors: "#fff",
        width: 0,
        dashArray: 0,
      },
      fill: {
        type: 'solid',
      },
      plotOptions: {
  
        pie: {
          expandOnClick: false,
          donut: {
            size: '78%',
            background: 'transparent',
            labels: {
              show: true,
              name: {
                show: true,
                fontSize: '20px',
                color: '#495057',
                offsetY: -4
              },
              value: {
                show: true,
                fontSize: '18px',
                color: undefined,
                offsetY: 8,
                formatter: function (val) {
                  return val + "%"
                }
              },
              total: {
                show: true,
                showAlways: true,
                label: 'کل',
                fontSize: '22px',
                fontWeight: 600,
                color: '#495057',
              }
    
            }
          }
        }
      },
      colors: ["var(--primary-color)", "rgb(227, 84, 212)", "rgb(255, 93, 159)"],
    };
    document.querySelector("#portfolio").innerHTML = " ";
    var chart = new ApexCharts(document.querySelector("#portfolio"), options);
    chart.render();


    
  /* Visitors Report */
  var options = {
    series: [
      {
        name: "هفته جاری",
        data: [25, 50, 30, 55, 20, 45, 30],
        type: 'column',
      },
      {
        name: "هفته گذشته",
        data: [35, 25, 40, 30, 45, 35, 60],
        type: 'column',
      }
    ],
    chart: {
      height: 267,
      type: 'line',
      toolbar: {
        show: false
      },
      dropShadow: {
        enabled: true,
        enabledOnSeries: undefined,
        top: 7,
        left: 0,
        blur: 1,
        color: ["transparent", "rgb(227, 84, 212)"],
        opacity: 0.05,
      },
    },
    plotOptions: {
      bar: {
        columnWidth: '35%',
        borderRadius: [2],
      }
    },
    colors: ['var(--primary-color)', 'rgb(227, 84, 212)'],
    dataLabels: {
      enabled: false,
    },
    stroke: {
      curve: 'smooth',
      width: 2,
      dashArray: [0, 0],
    },
    grid: {
      borderColor: "#f1f1f1",
      strokeDashArray: 2,
      xaxis: {
        lines: {
          show: true
        }
      },
      yaxis: {
        lines: {
          show: true
        }
      }
    },
    yaxis: {
      show: false,
      axisBorder: {
        show: false,
      },
      axisTicks: {
        show: false,
      }
    },
    xaxis: {
      categories: ['شنبه', 'یکشنبه', 'دوشنبه', 'سه شنبه', 'چهارشنبه', 'پنج شنبه', 'جمعه'],
      show: false,
      axisBorder: {
        show: false,
        color: 'rgba(119, 119, 142, 0.05)',
        offsetX: 0,
        offsetY: 0,
      },
      axisTicks: {
        show: false,
        borderType: 'solid',
        color: 'rgba(119, 119, 142, 0.05)',
        width: 6,
        offsetX: 0,
        offsetY: 0
      },
      labels: {
        rotate: -90,
      }
    },
    legend: {
      show: true,
      position: "bottom",
      offsetX: 0,
      offsetY: 8,
      markers: {
        size: 5,
        shape: "circle",
        strokeWidth: 0,
        strokeColor: '#fff',
        fillColors: undefined,
        radius: 12,
        customHTML: undefined,
        onClick: undefined,
        offsetX: 0,
        offsetY: 0
      },
    },
  };
  var chart = new ApexCharts(document.querySelector("#visitors-report"), options);
  chart.render();
  /* Visitors Report */


  
  function generateDayWiseTimeSeries(baseval, count, yrange) {
    var i = 0;
    var series = [];
    while (i < count) {
      var x = baseval;
      var y =
        Math.floor(Math.random() * (yrange.max - yrange.min + 1)) + yrange.min;
  
      series.push([x, y]);
      baseval += 76400000;
      i++;
    }
    return series;
  }
   /* stocks chart */

   /* stock-1 chart */
var spark1 = {
  chart: {
      type: 'line',
      height: 50,
      width: 120,
      sparkline: {
          enabled: true
      },
      dropShadow: {
          enabled: false,
          enabledOnSeries: undefined,
          top: 0,
          left: 0,
          blur: 3,
          color: '#000',
          opacity: 0.1
      }
  },
  grid: {
      show: false,
      xaxis: {
          lines: {
              show: false
          }
      },
      yaxis: {
          lines: {
              show: false
          }
      },
  },
  stroke: {
      show: true,
      curve: 'straight',
      lineCap: 'butt',
      colors: undefined,
      width: 1.5,
      dashArray: 2,
  },
  fill: {
      gradient: {
          enabled: false
      }
  },
  series: [{
      name: 'مقدار',
      data: [15, 42, 22, 42, 35, 32, 56, 35]
  }],
  yaxis: {
      min: 0,
      show: false
  },
  xaxis: {
      show: false,
      axisTicks: {
          show: false
      },
      axisBorder: {
          show: false
      }
  },
  yaxis: {
      axisBorder: {
          show: false
      },
  },
  colors: ['rgba(33, 206, 158, 0.4)'],

}
document.getElementById('stock-1').innerHTML = '';
var spark1 = new ApexCharts(document.querySelector("#stock-1"), spark1);
spark1.render();

   /* stock-1 chart */

   /* stock-2 chart */
var spark2 = {
  chart: {
      type: 'line',
      height: 50,
      width: 120,
      sparkline: {
          enabled: true
      },
      dropShadow: {
          enabled: false,
          enabledOnSeries: undefined,
          top: 0,
          left: 0,
          blur: 3,
          color: '#000',
          opacity: 0.1
      }
  },
  grid: {
      show: false,
      xaxis: {
          lines: {
              show: false
          }
      },
      yaxis: {
          lines: {
              show: false
          }
      },
  },
  stroke: {
      show: true,
      curve: 'straight',
      lineCap: 'butt',
      colors: undefined,
      width: 1.5,
      dashArray: 2,
  },
  fill: {
      gradient: {
          enabled: false
      }
  },
  series: [{
      name: 'مقدار',
      data: [15, 42, 22, 42, 35, 32, 56, 35]
  }],
  yaxis: {
      min: 0,
      show: false
  },
  xaxis: {
      show: false,
      axisTicks: {
          show: false
      },
      axisBorder: {
          show: false
      }
  },
  yaxis: {
      axisBorder: {
          show: false
      },
  },
  colors: ['rgba(251, 66, 66, 0.4)'],

}
document.getElementById('stock-2').innerHTML = '';
var spark2 = new ApexCharts(document.querySelector("#stock-2"), spark2);
spark2.render();

   /* stock-2 chart */

   /* stock-3 chart */
var spark3 = {
  chart: {
      type: 'line',
      height: 50,
      width: 120,
      sparkline: {
          enabled: true
      },
      dropShadow: {
          enabled: false,
          enabledOnSeries: undefined,
          top: 0,
          left: 0,
          blur: 3,
          color: '#000',
          opacity: 0.1
      }
  },
  grid: {
      show: false,
      xaxis: {
          lines: {
              show: false
          }
      },
      yaxis: {
          lines: {
              show: false
          }
      },
  },
  stroke: {
      show: true,
      curve: 'straight',
      lineCap: 'butt',
      colors: undefined,
      width: 1.5,
      dashArray: 2,
  },
  fill: {
      gradient: {
          enabled: false
      }
  },
  series: [{
      name: 'مقدار',
      data: [15, 42, 22, 42, 35, 32, 56, 35]
  }],
  yaxis: {
      min: 0,
      show: false
  },
  xaxis: {
      show: false,
      axisTicks: {
          show: false
      },
      axisBorder: {
          show: false
      }
  },
  yaxis: {
      axisBorder: {
          show: false
      },
  },
  colors: ['rgba(33, 206, 158, 0.4)'],

}
document.getElementById('stock-3').innerHTML = '';
var spark3 = new ApexCharts(document.querySelector("#stock-3"), spark3);
spark3.render();

   /* stock-3 chart */

   /* stock-4 chart */
var spark4 = {
  chart: {
      type: 'line',
      height: 50,
      width: 120,
      sparkline: {
          enabled: true
      },
      dropShadow: {
          enabled: false,
          enabledOnSeries: undefined,
          top: 0,
          left: 0,
          blur: 3,
          color: '#000',
          opacity: 0.1
      }
  },
  grid: {
      show: false,
      xaxis: {
          lines: {
              show: false
          }
      },
      yaxis: {
          lines: {
              show: false
          }
      },
  },
  stroke: {
      show: true,
      curve: 'straight',
      lineCap: 'butt',
      colors: undefined,
      width: 1.5,
      dashArray: 2,
  },
  fill: {
      gradient: {
          enabled: false
      }
  },
  series: [{
      name: 'مقدار',
      data: [15, 42, 22, 42, 35, 32, 56, 35]
  }],
  yaxis: {
      min: 0,
      show: false
  },
  xaxis: {
      show: false,
      axisTicks: {
          show: false
      },
      axisBorder: {
          show: false
      }
  },
  yaxis: {
      axisBorder: {
          show: false
      },
  },
  colors: ['rgba(251, 66, 66, 0.4)'],

}
document.getElementById('stock-4').innerHTML = '';
var spark4 = new ApexCharts(document.querySelector("#stock-4"), spark4);
spark4.render();

   /* stock-4 chart */

   /* stock-5 chart */
var spark5 = {
  chart: {
      type: 'line',
      height: 50,
      width: 120,
      sparkline: {
          enabled: true
      },
      dropShadow: {
          enabled: false,
          enabledOnSeries: undefined,
          top: 0,
          left: 0,
          blur: 3,
          color: '#000',
          opacity: 0.1
      }
  },
  grid: {
      show: false,
      xaxis: {
          lines: {
              show: false
          }
      },
      yaxis: {
          lines: {
              show: false
          }
      },
  },
  stroke: {
      show: true,
      curve: 'straight',
      lineCap: 'butt',
      colors: undefined,
      width: 1.5,
      dashArray: 2,
  },
  fill: {
      gradient: {
          enabled: false
      }
  },
  series: [{
      name: 'مقدار',
      data: [15, 42, 22, 42, 35, 32, 56, 35]
  }],
  yaxis: {
      min: 0,
      show: false
  },
  xaxis: {
      show: false,
      axisTicks: {
          show: false
      },
      axisBorder: {
          show: false
      }
  },
  yaxis: {
      axisBorder: {
          show: false
      },
  },
  colors: ['rgba(33, 206, 158, 0.4)'],

}
document.getElementById('stock-5').innerHTML = '';
var spark5 = new ApexCharts(document.querySelector("#stock-5"), spark5);
spark5.render();

   /* stock-5 chart */

   /* stock-6 chart */
var spark6 = {
  chart: {
      type: 'line',
      height: 50,
      width: 120,
      sparkline: {
          enabled: true
      },
      dropShadow: {
          enabled: false,
          enabledOnSeries: undefined,
          top: 0,
          left: 0,
          blur: 3,
          color: '#000',
          opacity: 0.1
      }
  },
  grid: {
      show: false,
      xaxis: {
          lines: {
              show: false
          }
      },
      yaxis: {
          lines: {
              show: false
          }
      },
  },
  stroke: {
      show: true,
      curve: 'straight',
      lineCap: 'butt',
      colors: undefined,
      width: 1.5,
      dashArray: 2,
  },
  fill: {
      gradient: {
          enabled: false
      }
  },
  series: [{
      name: 'مقدار',
      data: [15, 42, 22, 42, 35, 32, 56, 35]
  }],
  yaxis: {
      min: 0,
      show: false
  },
  xaxis: {
      show: false,
      axisTicks: {
          show: false
      },
      axisBorder: {
          show: false
      }
  },
  yaxis: {
      axisBorder: {
          show: false
      },
  },
  colors: ['rgba(33, 206, 158, 0.4)'],

}
document.getElementById('stock-6').innerHTML = '';
var spark6 = new ApexCharts(document.querySelector("#stock-6"), spark6);
spark6.render();

   /* stock-6 chart */

   /* stock-7 chart */
var spark7 = {
  chart: {
      type: 'line',
      height: 50,
      width: 120,
      sparkline: {
          enabled: true
      },
      dropShadow: {
          enabled: false,
          enabledOnSeries: undefined,
          top: 0,
          left: 0,
          blur: 3,
          color: '#000',
          opacity: 0.1
      }
  },
  grid: {
      show: false,
      xaxis: {
          lines: {
              show: false
          }
      },
      yaxis: {
          lines: {
              show: false
          }
      },
  },
  stroke: {
      show: true,
      curve: 'straight',
      lineCap: 'butt',
      colors: undefined,
      width: 1.5,
      dashArray: 2,
  },
  fill: {
      gradient: {
          enabled: false
      }
  },
  series: [{
      name: 'مقدار',
      data: [15, 42, 22, 42, 35, 32, 56, 35]
  }],
  yaxis: {
      min: 0,
      show: false
  },
  xaxis: {
      show: false,
      axisTicks: {
          show: false
      },
      axisBorder: {
          show: false
      }
  },
  yaxis: {
      axisBorder: {
          show: false
      },
  },
  colors: ['rgba(251, 66, 66, 0.4)'],

}
document.getElementById('stock-7').innerHTML = '';
var spark7 = new ApexCharts(document.querySelector("#stock-7"), spark7);
spark7.render();

   /* stock-7 chart */