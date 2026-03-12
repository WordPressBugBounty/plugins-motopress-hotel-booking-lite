"use strict";

(function ($) {
  $(function () {
    var EarningsReport = function EarningsReport(placeholder, data) {
      var _this = this;
      this.data = data;
      this.options = {
        xaxis: {
          mode: "time",
          timeBase: "milliseconds",
          autoScale: "none",
          min: data.startDate,
          max: data.endDate,
          timeformat: data.timeformat,
          tickSize: data.tickSize,
          alignTicksWithAxis: 1
        },
        yaxes: [{
          position: "left",
          min: 0,
          tickDecimals: 0,
          alignTicksWithAxis: 1
        }, {
          position: "right",
          min: 0,
          tickFormatter: function tickFormatter(val, axis) {
            return data.currencySymbol + val.toFixed(axis.tickDecimals);
          }
        }],
        grid: {
          hoverable: true,
          borderWidth: 1,
          borderColor: '#eee',
          color: '#bebebe'
        },
        legend: {
          show: false
        },
        series: {
          curvedLines: {
            apply: true,
            active: true,
            monotonicFit: true
          }
        }
      };
      function filterDataByDataType(data, highlighted) {
        var plotData = data.plotData,
          currencySymbol = data.currencySymbol,
          dataFilters = [],
          dataTypes = [],
          plot = [];
        var dataFilterInputs = document.getElementsByName('mphb-chart-data-filter[]');
        var dataTypeInputs = document.getElementsByName('mphb-chart-data-type-filter[]');
        for (var i = 0; i < dataFilterInputs.length; i++) {
          dataFilters.push(dataFilterInputs[i].value);
        }
        for (var i = 0; i < dataTypeInputs.length; i++) {
          dataTypes.push(dataTypeInputs[i].value);
        }

        // Move highlighted plots to front
        if (highlighted) {
          var n = [];
          var toMove = [];
          plotData.forEach(function (series, ind) {
            if (series.dataType == highlighted) {
              n.push(ind);
              toMove.push(series);
            }
          });
          if (n) {
            plotData = plotData.filter(function (series, ind) {
              return !n.includes(ind);
            });
            toMove.forEach(function (series) {
              plotData.push(series);
            });
          }
        }
        for (var i = 0; i < plotData.length; i++) {
          var plotDataType = plotData[i].dataType,
            plotDataFilter = plotData[i].dataFilter,
            color = plotData[i].color,
            showBars = plotData[i].plotType && plotData[i].plotType == 'bars',
            showDashes = plotData[i].plotType && plotData[i].plotType == 'dashes',
            dashLength = plotData[i].dashLength,
            d = plotData[i].plotArray,
            yaxis = plotData[i].dataFilter === 'totalBookings' ? 1 : 2;
          var lineWidth = 1;
          var label = data.filters[plotData[i].dataFilter] || '';
          var symbol = '';
          if (label) {
            symbol = label + ': ';
          }
          if (yaxis === 2) {
            symbol += currencySymbol;
          }
          if (dataTypes.length <= 0 || !dataTypes.includes(plotDataType) || dataFilters.length <= 0 || !dataFilters.includes(plotDataFilter)) {
            continue;
          }
          if (highlighted && plotDataType == highlighted) {
            lineWidth = 2;
          }
          plot.push({
            color: color,
            lines: {
              show: !showBars && !showDashes,
              lineWidth: lineWidth,
              fill: false
            },
            dashes: {
              show: showDashes,
              lineWidth: lineWidth,
              dashLength: dashLength
            },
            yaxis: yaxis,
            data: d,
            hoverable: false
          }, {
            symbol: symbol,
            color: color,
            points: {
              show: !showBars,
              radius: 3,
              lineWidth: 1,
              fillColor: '#fff',
              fill: true
            },
            yaxis: yaxis,
            data: d,
            curvedLines: {
              apply: false
            }
          });
        }
        return plot;
      }
      function showTooltip(x, y, contents) {
        jQuery('<div class="mphb-tooltip">' + contents + '</div>').css({
          left: x + 10,
          top: y - 13,
          borderRadius: 3,
          opacity: 0.95,
          position: "absolute"
        }).appendTo('body').fadeIn(200);
      }
      function removeTooltip() {
        jQuery('.mphb-tooltip').stop().remove();
      }
      function drawPlot(options, plot) {
        jQuery.plot(placeholder, plot, options);
        initTooltips();
      }
      function initTooltips() {
        jQuery(placeholder).bind("plothover", function (event, pos, item) {
          removeTooltip();
          if (!pos.x || !(pos.y1 || pos.y2)) return;
          if (item) {
            var number = item.series.yaxis.n === 1 ? item.datapoint[1].toFixed(0) : item.datapoint[1].toFixed(2),
              content = item.series.symbol + number;
            showTooltip(item.pageX, item.pageY, content);
          } else {
            removeTooltip();
          }
        });
        jQuery(placeholder).bind("plothovercleanup", function (event, pos, item) {
          removeTooltip();
        });
      }
      function highlightPlot(e) {
        jQuery(this).addClass('mphb-highlighted');
        var data = e.data[0],
          options = e.data[1],
          dataType = jQuery(this).data('datatype'),
          plot = filterDataByDataType(data, dataType);
        drawPlot(options, plot);
      }
      function unhighlightPlot(e) {
        jQuery(this).removeClass('mphb-highlighted');
        var data = e.data[0],
          options = e.data[1],
          plot = filterDataByDataType(data);
        drawPlot(options, plot);
      }
      var filterPlot = function filterPlot() {
        var plot = filterDataByDataType(_this.data);
        drawPlot(_this.options, plot);
      };
      function initFilters() {
        new MultiSelect(document.getElementById('mphb-chart-data-filter'), {
          placeholder: 'Select options',
          selectAll: false,
          search: false,
          min: 1,
          onChange: function onChange() {
            filterPlot();
          }
        });
        new MultiSelect(document.getElementById('mphb-chart-data-type-filter'), {
          placeholder: 'Select options',
          selectAll: false,
          search: false,
          min: 1,
          onChange: function onChange() {
            filterPlot();
          }
        });
      }
      jQuery('.mphb-chart-legend-item').on('mouseenter', [this.data, this.options], highlightPlot);
      jQuery('.mphb-chart-legend-item').on('mouseleave', [this.data, this.options], unhighlightPlot);
      this.plot = function () {
        initFilters();
        var plot = filterDataByDataType(this.data);
        drawPlot(this.options, plot);
      };
    };
    $('#mphb-dates-range-select').on('change', function () {
      if ($(this).val() == 'custom') {
        $('#mphb-dates-range-show').removeClass('mphb-invisible');
      } else {
        $('#mphb-dates-range-show').addClass('mphb-invisible');
      }
    });
    var data = JSON.parse(ReportData.data);
    var g = new EarningsReport("#mphb-earnings-report", data);
    g.plot();
  });
})(jQuery);