$(function(){
	/**
	 * Skies theme for Highcharts JS
	 * @author Torstein Honsi
	 */

	Highcharts.theme = {
		colors: ["#514F78", "#42A07B", "#9B5E4A", "#72727F", "#1F949A", "#82914E", "#86777F", "#42A07B"],
		chart: {
			className: 'skies',
			borderWidth: 0,
			plotShadow: true,
			plotBackgroundImage: 'http://www.highcharts.com/demo/gfx/skies.jpg',
			plotBackgroundColor: {
				linearGradient: [0, 0, 250, 500],
				stops: [
					[0, 'rgba(255, 255, 255, 1)'],
					[1, 'rgba(255, 255, 255, 0)']
				]
			},
			plotBorderWidth: 1
		},
		title: {
			style: {
				color: '#3E576F',
				font: '16px Lucida Grande, Lucida Sans Unicode, Verdana, Arial, Helvetica, sans-serif'
			}
		},
		subtitle: {
			style: {
				color: '#6D869F',
				font: '12px Lucida Grande, Lucida Sans Unicode, Verdana, Arial, Helvetica, sans-serif'
			}
		},
		xAxis: {
			gridLineWidth: 0,
			lineColor: '#C0D0E0',
			tickColor: '#C0D0E0',
			labels: {
				style: {
					color: '#666',
					fontWeight: 'bold'
				}
			},
			title: {
				style: {
					color: '#666',
					font: '12px Lucida Grande, Lucida Sans Unicode, Verdana, Arial, Helvetica, sans-serif'
				}
			}
		},
		yAxis: {
			alternateGridColor: 'rgba(255, 255, 255, .5)',
			lineColor: '#C0D0E0',
			tickColor: '#C0D0E0',
			tickWidth: 1,
			labels: {
				style: {
					color: '#666',
					fontWeight: 'bold'
				}
			},
			title: {
				style: {
					color: '#666',
					font: '12px Lucida Grande, Lucida Sans Unicode, Verdana, Arial, Helvetica, sans-serif'
				}
			}
		},
		legend: {
			itemStyle: {
				font: '9pt Trebuchet MS, Verdana, sans-serif',
				color: '#3E576F'
			},
			itemHoverStyle: {
				color: 'black'
			},
			itemHiddenStyle: {
				color: 'silver'
			}
		},
		labels: {
			style: {
				color: '#3E576F'
			}
		}
	};

	// Apply the theme
	var highchartsOptions = Highcharts.setOptions(Highcharts.theme);
	
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