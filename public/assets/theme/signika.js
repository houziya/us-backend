$(function(){
	/**
	 * Sand-Signika theme for Highcharts JS
	 * @author Torstein Honsi
	 */

	// Load the fonts
	Highcharts.createElement('link', {
		href: 'http://fonts.googleapis.com/css?family=Signika:400,700',
		rel: 'stylesheet',
		type: 'text/css'
	}, null, document.getElementsByTagName('head')[0]);

	// Add the background image to the container
	Highcharts.wrap(Highcharts.Chart.prototype, 'getContainer', function (proceed) {
		proceed.call(this);
		this.container.style.background = 'url(http://www.highcharts.com/samples/graphics/sand.png)';
	});


	Highcharts.theme = {
		colors: ["#f45b5b", "#8085e9", "#8d4654", "#7798BF", "#aaeeee", "#ff0066", "#eeaaee",
			"#55BF3B", "#DF5353", "#7798BF", "#aaeeee"],
		chart: {
			backgroundColor: null,
			style: {
				fontFamily: "Signika, serif"
			}
		},
		title: {
			style: {
				color: 'black',
				fontSize: '16px',
				fontWeight: 'bold'
			}
		},
		subtitle: {
			style: {
				color: 'black'
			}
		},
		tooltip: {
			borderWidth: 0
		},
		legend: {
			itemStyle: {
				fontWeight: 'bold',
				fontSize: '13px'
			}
		},
		xAxis: {
			labels: {
				style: {
					color: '#6e6e70'
				}
			}
		},
		yAxis: {
			labels: {
				style: {
					color: '#6e6e70'
				}
			}
		},
		plotOptions: {
			series: {
				shadow: true
			},
			candlestick: {
				lineColor: '#404048'
			},
			map: {
				shadow: false
			}
		},

		// Highstock specific
		navigator: {
			xAxis: {
				gridLineColor: '#D0D0D8'
			}
		},
		rangeSelector: {
			buttonTheme: {
				fill: 'white',
				stroke: '#C0C0C8',
				'stroke-width': 1,
				states: {
					select: {
						fill: '#D0D0D8'
					}
				}
			}
		},
		scrollbar: {
			trackBorderColor: '#C0C0C8'
		},

		// General
		background2: '#E0E0E8'
		
	};

	// Apply the theme
	Highcharts.setOptions(Highcharts.theme);
	
	$('.table-striped').highcharts({
        chart: {
            type: 'line'
        },
        title: {
            text: 'Daily Average Data'
        },
        subtitle: {
            /*text: 'Source: WorldClimate.com'*/
        },
        xAxis: {
            categories: temp
        },
        yAxis: {
            title: {
                text: 'Data (个)'
            }
        },
        tooltip: {
            enabled: false,
            formatter: function() {
                return '<b>'+ this.series.name +'</b><br>'+this.x +': '+ this.y +'个';
            }
        },
        plotOptions: {
            line: {
                dataLabels: {
                    enabled: true
                },
                enableMouseTracking: false
            }
        },
        series: [{
            name: '分享微信',
            data: share_wxss
            
        }, {
            name: '分享朋友圈',
            data: share_wxst
        },{
            name: '分享QQ',
            data: share_qq
        },{
            name: '分享QQ空间',
            data: share_url
        },{
            name: '分享微博',
            data: share_sina
        }]
    });
})