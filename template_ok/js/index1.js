$(function(e) {
    'use strict'

    // FLOAT CHART
    // $.plot('#flotback-chart', [{
    //     data: dashData10,
    //     color: 'rgba(255,255,255, 0.2)',
    //     lines: {
    //         lineWidth: 1
    //     }
    // }], {
    //     series: {
    //         stack: 0,
    //         shadowSize: 0,
    //         lines: {
    //             show: true,
    //             lineWidth: 1.8,
    //             fill: true,
    //             fillColor: {
    //                 colors: [{
    //                     opacity: 0
    //                 }, {
    //                     opacity: 0.3
    //                 }]
    //             }
    //         }
    //     },
    //     grid: {
    //         borderWidth: 0,
    //         labelMargin: 5,
    //         hoverable: true
    //     },
    //     yaxis: {
    //         show: false,
    //         color: 'rgba(72, 94, 144, .1)',
    //         min: 0,
    //         max: 130,
    //         font: {
    //             size: 10,
    //             color: '#8392a5'
    //         }
    //     },
    //     xaxis: {
    //         show: false,
    //         color: 'rgba(72, 94, 144, .1)',
    //         ticks: [
    //             [0, '10'],
    //             [10, '20'],
    //             [20, '30'],
    //             [30, '40'],
    //             [40, '50'],
    //             [50, '60'],
    //             [60, '70'],
    //             [70, '80'],
    //             [80, '90'],
    //             [90, '100'],
    //             [100, '110'],
    //             [120, '120'],
    //         ],
    //         font: {
    //             size: 10,
    //             color: '#8392a5'
    //         },
    //         reserveSpace: false
    //     }
    // });

    // RECENT ORDERS
    // var myCanvas = document.getElementById("recentorders");
    // myCanvas.height = "225";
    // var myChart = new Chart(myCanvas, {
    //     type: 'bar',
    //     data: {
    //         labels: ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"],
    //         datasets: [{
    //             label: 'This Month',
    //             data: [17, 40, 18, 40, 18, 20, 12],
    //             backgroundColor: '#61c9fc',
    //             borderWidth: 2,
    //             hoverBackgroundColor: '#61c9fc',
    //             hoverBorderWidth: 0,
    //             borderColor: '#61c9fc',
    //             hoverBorderColor: '#61c9fc',
    //             borderDash: [5, 2],
    //             borderRadius: 5,
    //             borderWidth: 0,
    //             barPercentage:.40,
    //         }, {
    //             label: 'This Month',
    //             data: [22, 48, 58, 55, 30, 58, 48],
    //             backgroundColor: '#f38ff3',
    //             borderWidth: 2,
    //             hoverBackgroundColor: '#f38ff3',
    //             hoverBorderWidth: 0,
    //             borderColor: '#f38ff3',
    //             hoverBorderColor: '#f38ff3',
    //             borderDash: [5, 2],
    //             borderRadius: 5,
    //             borderWidth: 0,
    //             barPercentage:.40,
    //         }],
    //     },
    //     options: {
    //         responsive: true,
    //         maintainAspectRatio: false,
    //         layout: {
    //             padding: {
    //                 left: 0,
    //                 right: 0,
    //                 top: 0,
    //                 bottom: 0
    //             }
    //         },
    //         tooltips: {
    //             enabled: false,
    //         },
    //         plugins: {
    //             legend: {
    //                 display: false,
    //                 labels: {
    //                     display: false
    //                 }
    //             },
    //           grid: {
    //                display: false,
    //                color:"transparent",
    //           },
    //         },
    //         scales: {
    //             y: {
    //                 display: false,
    //                 ticks: {
    //                     beginAtZero: true,
    //                     stepSize: 25,
    //                     suggestedMin: 0,
    //                     suggestedMax: 100,
    //                     color:'transparent',
    //                     userCallback: function(tick) {
    //                         return tick.toString() + '%';
    //                     }
    //                 },
    //             },
    //             x: {
    //                 display: false,
    //                 barPercentage: 0.4,
    //                 barValueSpacing: 0,
    //                 barDatasetSpacing: 0,
    //                 barRadius: 0,
    //                 stacked: false,
    //                 ticks: {
    //                     beginAtZero: false,
    //                     color: "transparent",
    //                 },
    //
    //             }
    //         },
    //         elements: {
    //             point: {
    //                 radius: 0
    //             }
    //         },
    //     }
    // });

    // SALES CHART
    // var ctx = document.getElementById('saleschart').getContext('2d');
    // ctx.height = 10;
    // var myChart = new Chart(ctx, {
    //     type: 'bar',
    //     data: {
    //         labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
    //         datasets: [{
    //             label: 'Total Sales',
    //             data: [18, 10, 14, 9, 7, 12, 13],
    //              borderWidth: 3,
    //             backgroundColor: [
    //                 'rgba(5, 195, 251, 0.2)',
    //                 'rgba(5, 195, 251, 0.2)',
    //                 '#05c3fb',
    //                 'rgba(5, 195, 251, 0.2)',
    //                 'rgba(5, 195, 251, 0.2)',
    //                 '#05c3fb',
    //                 '#05c3fb'
    //             ],
    //             borderColor: [
    //                 'rgba(5, 195, 251, 0.7)',
    //                 'rgba(5, 195, 251, 0.7)',
    //                 '#05c3fb',
    //                 'rgba(5, 195, 251, 0.7)',
    //                 'rgba(5, 195, 251, 0.7)',
    //                 '#05c3fb',
    //                 '#05c3fb'
    //             ],
    //             borderRadius: 5,
    //             borderWidth: 2,
    //             barPercentage:.55,
    //         }, ]
    //     },
    //     options: {
    //         maintainAspectRatio: false,
    //         legend: {
    //             display: false
    //         },
    //         responsive: true,
    //         tooltips: {
    //             enabled: false,
    //         },
    //         scales: {
    //             x: {
    //                 categoryPercentage: 1.0,
    //                 barPercentage: 1.0,
    //                 barDatasetSpacing: 0,
    //                 display: false,
    //                 barThickness: 5,
    //                 barRadius: 0,
    //                 gridLines: {
    //                     color: 'transparent',
    //                     zeroLineColor: 'transparent'
    //                 },
    //                 ticks: {
    //                     fontSize: 2,
    //                     color: 'transparent'
    //                 }
    //             },
    //             y: {
    //                 display: false,
    //                 ticks: {
    //                     display: false,
    //                 }
    //             }
    //         },
    //         title: {
    //             display: false,
    //         },
    //         elements: {
    //             point: {
    //                 radius: 0
    //             }
    //         },
    //         plugins: {
    //             legend: {
    //                 display: false
    //             },
    //             tooltip: {
    //               enabled: false
    //             },
    //         }
    //     }
    // });

    // CHARTJS SALES CHART CLOSED

    // LEADS CHART
    // var ctx = document.getElementById('leadschart').getContext('2d');
    // ctx.height = 10;
    // var myChart = new Chart(ctx, {
    //     type: 'line',
    //     data:{
    //         labels: ['Date 1', 'Date 2', 'Date 3', 'Date 4', 'Date 5', 'Date 6', 'Date 7', 'Date 8', 'Date 9', 'Date 10', 'Date 11', 'Date 12', 'Date 13', 'Date 14', 'Date 15'],
    //             datasets: [{
    //                 label: 'Total Sales',
    //                 data: [45, 23, 32, 67, 49, 72, 52, 55, 46, 54, 32, 74, 88, 36, 36, 32, 48, 54],
    //                 borderColor: '#f46ef4',
    //                 borderWidth: 2.5,
    //                 fill: false,
    //                 tension: 0.6,
    //                 pointBorderColor: 'transparent',
    //                 pointBackgroundColor: 'transparent',
    //
    //             } ],
    //     },
    //     options: {
    //         maintainAspectRatio: false,
    //         legend: {
    //             display: false
    //         },
    //         responsive: true,
    //         tooltips: {
    //             enabled: false,
    //         },
    //         scales: {
    //             x:{
    //                 categoryPercentage: 1.0,
    //                 barPercentage: 1.0,
    //                 barDatasetSpacing: 0,
    //                 display: false,
    //                 barThickness: 5,
    //                 gridLines: {
    //                     color: 'transparent',
    //                     zeroLineColor: 'transparent'
    //                 },
    //                 ticks: {
    //                     fontSize: 2,
    //                     fontColor: 'transparent'
    //                 }
    //             },
    //             y: {
    //                 display: false,
    //                 ticks: {
    //                     display: false,
    //                 }
    //             }
    //         },
    //         title: {
    //             display: false,
    //         },
    //         plugins: {
    //             legend: {
    //                 display: false
    //             },
    //             tooltip: {
    //               enabled: false
    //             },
    //         }
    //     }
    // });
    // CHARTJS LEADS CHART CLOSED

    // PROFIT CHART
    
    // var ctx = document.getElementById('profitchart').getContext('2d');
    // ctx.height = 10;
    // var myChart = new Chart(ctx, {
    //     type: 'bar',
    //     data: {
    //         labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
    //         datasets: [{
    //             label: 'Total Sales',
    //             data: [5, 7, 2, 3, 1, 5, 9],
    //             backgroundColor: '#4ecc48',
    //             borderRadius: 5,
    //             barPercentage:.55,
    //         }, ]
    //     },
    //     options: {
    //         maintainAspectRatio: false,
    //         legend: {
    //             display: false
    //         },
    //         responsive: true,
    //         tooltips: {
    //             enabled: false,
    //         },
    //         scales: {
    //             x: {
    //                 categoryPercentage: 1.0,
    //                 barPercentage: 1.0,
    //                 barDatasetSpacing: 0,
    //                 display: false,
    //                 barThickness: 5,
    //                 barRadius: 0,
    //                 gridLines: {
    //                     color: 'transparent',
    //                     zeroLineColor: 'transparent'
    //                 },
    //                 ticks: {
    //                     fontSize: 2,
    //                     fontColor: 'transparent'
    //                 }
    //             },
    //             y: {
    //                 display: false,
    //                 ticks: {
    //                     display: false,
    //                 }
    //             }
    //         },
    //         title: {
    //             display: false,
    //         },
    //         elements: {
    //             point: {
    //                 radius: 0
    //             }
    //         },
    //         plugins: {
    //             legend: {
    //                 display: false
    //             },
    //             tooltip: {
    //               enabled: false
    //             },
    //         }
    //     }
    // });
    //
    // // COST CHART
    // var ctx = document.getElementById('costchart').getContext('2d');
    // ctx.height = 10;
    // var myChart = new Chart(ctx, {
    //     type: 'line',
    //     data: {
    //         labels: ['Date 1', 'Date 2', 'Date 3', 'Date 4', 'Date 5', 'Date 6', 'Date 7', 'Date 8', 'Date 9', 'Date 10', 'Date 11', 'Date 12', 'Date 13', 'Date 14', 'Date 15', 'Date 16', 'Date 17'],
    //         datasets: [{
    //             label: 'Total Sales',
    //             data: [28, 56, 36, 32, 48, 54, 37, 58, 66, 53, 21, 24, 14, 45, 0, 32, 67, 49, 52, 55, 46, 54, 130],
    //             backgroundColor: 'transparent',
    //             borderColor: '#f7ba48',
    //             borderWidth: 2.5,
    //             fill: false,
    //             tension: 0.6,
    //             pointBorderColor: 'transparent',
    //             pointBackgroundColor: 'transparent',
    //         }, ]
    //     },
    //     options: {
    //         maintainAspectRatio: false,
    //         legend: {
    //             display: false
    //         },
    //         responsive: true,
    //         tooltips: {
    //             enabled: false,
    //         },
    //         scales: {
    //             x: {
    //                 categoryPercentage: 1.0,
    //                 barPercentage: 1.0,
    //                 barDatasetSpacing: 0,
    //                 display: false,
    //                 barThickness: 5,
    //                 gridLines: {
    //                     color: 'transparent',
    //                     zeroLineColor: 'transparent'
    //                 },
    //                 ticks: {
    //                     fontSize: 2,
    //                     fontColor: 'transparent'
    //                 }
    //             },
    //             y: {
    //                 display: false,
    //                 ticks: {
    //                     display: false,
    //                 }
    //             }
    //         },
    //         title: {
    //             display: false,
    //         },
    //         plugins: {
    //             legend: {
    //                 display: false
    //             },
    //             tooltip: {
    //               enabled: false
    //             },
    //         }
    //     }
    // });
    // COST CHART CLOSED

    // DATA TABLE
    // $('#data-table').DataTable({
    //     "order": [
    //         [0, "desc"]
    //     ],
    //     order: [],
    //     columnDefs: [{ orderable: false, targets: [0, 4, 5] }],
    //     language: {
    //         searchPlaceholder: 'Search...',
    //         sSearch: '',
    //     }
    // });
    //
    // // SELECT2
    // $('.select2').select2({
    //     minimumResultsForSearch: Infinity
    // });
    //
    // // WORLD MAP MARKER
    // $('#world-map-markers1').vectorMap({
    //     map: 'world_mill_en',
    //     scaleColors: ['#6c5ffc', '#e82646', '#05c3fb'],
    //
    //     normalizeFunction: 'polynomial',
    //
    //     hoverOpacity: 0.7,
    //
    //     hoverColor: false,
    //
    //     regionStyle: {
    //
    //         initial: {
    //
    //             fill: '#edf0f5'
    //         }
    //     },
    //     markerStyle: {
    //         initial: {
    //             r: 6,
    //             'fill': '#6c5ffc',
    //             'fill-opacity': 0.9,
    //             'stroke': '#fff',
    //             'stroke-width': 9,
    //             'stroke-opacity': 0.2
    //         },
    //
    //         hover: {
    //             'stroke': '#fff',
    //             'fill-opacity': 1,
    //             'stroke-width': 1.5
    //         }
    //     },
    //     backgroundColor: 'transparent',
    //     markers: [{
    //         latLng: [40.3, -101.38],
    //         name: 'USA',
    //     }, {
    //         latLng: [22.5, 1.51],
    //         name: 'India'
    //     }, {
    //         latLng: [50.02, 80.55],
    //         name: 'Bahrain'
    //     }, {
    //         latLng: [3.2, 73.22],
    //         name: 'Maldives'
    //     }, {
    //         latLng: [35.88, 14.5],
    //         name: 'Malta'
    //     }, ]
    // });

});

function getCssValuePrefix() {
    'use strict'

    var retuenValue = ''; //default to standard syntax
    var prefixes = ['-o-', '-ms-', '-moz-', '-webkit-'];

    // Create a temporary DOM object for testing
    var dom = document.createElement('div');

    for (var i = 0; i < prefixes.length; i++) {
        // Attempt to set the style
        dom.style.background = prefixes[i] + 'linear-gradient(#ffffff, #000000)';

        // Detect if the style was successfully set
        if (dom.style.background) {
            retuenValue = prefixes[i];
        }
    }

    dom = null;
    dom.remove();

    return retuenValue;
}


    // TRANSACTIONS
    // var myCanvas = document.getElementById("transactions");
    // myCanvas.height = "330";
    //
    // var myCanvasContext = myCanvas.getContext("2d");
    // var gradientStroke1 = myCanvasContext.createLinearGradient(0, 80, 0, 280);
    // gradientStroke1.addColorStop(0,  'rgba(108, 95, 252, 0.8)');
    // gradientStroke1.addColorStop(1, 'rgba(108, 95, 252, 0.2) ');
    //
    // var gradientStroke2 = myCanvasContext.createLinearGradient(0, 80, 0, 280);
    // gradientStroke2.addColorStop(0,  'rgba(5, 195, 251, 0.8)');
    // gradientStroke2.addColorStop(1,'rgba(5, 195, 251, 0.2) ');
    // document.getElementById('transactions').innerHTML = '';
    //
    // var myChart;
    // myChart = new Chart(myCanvas, {
    //
    //     type: 'line',
    //     data: {
    //         labels: ["Jan", "Feb", "Mar", "Apr", "May", "June", "July", "Aug", "Sep", "Oct", "Nov", "Dec"],
    //         type: 'line',
    //         datasets: [{
    //             label: 'Total Sales',
    //             data: [100, 210, 180, 454, 454, 230, 230, 656, 656, 350, 350, 210],
    //             backgroundColor: gradientStroke1,
    //             borderColor: '#6c5ffc',
    //             pointBackgroundColor: '#fff',
    //             pointHoverBackgroundColor: gradientStroke1,
    //             pointBorderColor: '#6c5ffc',
    //             pointHoverBorderColor: gradientStroke1,
    //             pointBorderWidth: 0,
    //             pointRadius: 0,
    //             tension: 0.4,
    //             pointHoverRadius: 0,
    //             borderWidth: 3,
    //             fill: 'origin'
    //         }, {
    //             label: 'Total Orders',
    //             data: [200, 530, 110, 110, 480, 520, 780, 435, 475, 738, 454, 454],
    //             backgroundColor: 'transparent',
    //             borderColor: '#05c3fb',
    //             pointBackgroundColor: '#fff',
    //             pointHoverBackgroundColor: gradientStroke2,
    //             pointBorderColor: '#05c3fb',
    //             pointHoverBorderColor: gradientStroke2,
    //             pointBorderWidth: 0,
    //             pointRadius: 0,
    //             pointHoverRadius: 0,
    //             tension: 0.4,
    //             borderWidth: 3,
    //             fill: 'origin',
    //
    //         }]
    //     },
    //     options: {
    //         responsive: true,
    //         maintainAspectRatio: false,
    //         tooltips: {
    //             enabled: false,
    //         },
    //         legend: {
    //             display: false,
    //             labels: {
    //                 usePointStyle: false,
    //             },
    //         },
    //         plugins: {
    //             legend: {
    //                 display: false,
    //                 labels: {
    //                     display: false
    //                 }
    //             },
    //         },
    //         scales: {
    //             x: {
    //                 display: true,
    //                 gridLines: {
    //                     display: false,
    //                     drawBorder: false,
    //                     color: 'rgba(119, 119, 142, 0.08)'
    //                 },
    //                 ticks: {
    //                     color: "#9ba6b5",
    //                     autoSkip: true,
    //                 },
    //                 scaleLabel: {
    //                     display: false,
    //                     labelString: 'Month',
    //                     color: 'transparent'
    //                 },
    //                 grid: {
    //                     color:'transparent',
    //                     display:false,
    //                   },
    //                 gridLines: {
    //                     color: 'rgba(119, 119, 142, 0.2)'
    //                 },
    //             },
    //             y: {
    //                 ticks: {
    //                     min: 0,
    //                     max: 1050,
    //                     stepSize: 150,
    //                     color: "#9ba6b5",
    //                 },
    //                 display: true,
    //                 gridLines: {
    //                     display: true,
    //                     drawBorder: false,
    //                     zeroLineColor: 'rgba(142, 156, 173,0.1)',
    //                     color: "rgba(142, 156, 173,0.1)",
    //                 },
    //                 scaleLabel: {
    //                     display: false,
    //                     labelString: 'sales',
    //                     fontColor: 'transparent'
    //                 },
    //                 grid: {
    //                     color: "rgba(119, 119, 142, 0.1)",
    //                     borderColor:"rgba(119, 119, 142, 0.1)"
    //                   },
    //                 gridLines: {
    //                     color: 'rgba(119, 119, 142, 0.2)'
    //                 },
    //             }
    //         },
    //         title: {
    //             display: false,
    //             text: 'Normal Legend'
    //         }
    //     },
    // });
function index(myVarVal) {

    myCanvasContext = myCanvas.getContext("2d");
    gradientStroke1 = myCanvasContext.createLinearGradient(0, 80, 0, 280);
    gradientStroke1.addColorStop(0, hexToRgba(myVarVal, 0.8) || 'rgba(108, 95, 252, 0.8)');
    gradientStroke1.addColorStop(1, hexToRgba(myVarVal, 0.2) || 'rgba(108, 95, 252, 0.2) ');

    gradientStroke2 = myCanvasContext.createLinearGradient(0, 80, 0, 280);
    gradientStroke2.addColorStop(0, hexToRgba(myVarVal1, 0.8) || 'rgba(5, 195, 251, 0.8)');
    gradientStroke2.addColorStop(1, hexToRgba(myVarVal1, 0.8) || 'rgba(5, 195, 251, 0.2) ');

    myChart.data.datasets[0].borderColor = hexToRgba(myVarVal);
    myChart.data.datasets[0].pointBorderColor = hexToRgba(myVarVal);
    myChart.data.datasets[0].backgroundColor = gradientStroke1;
    myChart.data.datasets[0].pointHoverBackgroundColor = gradientStroke1;
    myChart.data.datasets[0].pointHoverBorderColor = gradientStroke1;

    myChart.data.datasets[1].pointHoverBackgroundColor = gradientStroke2;
    myChart.data.datasets[1].pointHoverBorderColor = gradientStroke2;
    
    myChart.update();
}