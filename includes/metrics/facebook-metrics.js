jQuery(document).ready(function() {

  jQuery('#metrics-sidemenu').foundation('down', jQuery('#contacts-menu'));

  let chartDiv = jQuery('#chart')
  chartDiv.empty().html(`
      <h3>Facebook Messages Activity</h3>
      <div id="facebook_messages_chart" style="min-height: 300px"></div>
      
      <h3>New Facebook Contacts</h3>
      <div id="facebook_contacts_chart" style="min-height: 300px"></div>
            
      <h3>Contact Assignment</h3>
      <div id="facebook_assignments_chart" style="min-height: 300px"></div>
      
      <h3>Contact Meetings</h3>
      <div id="facebook_meetings_chart" style="min-height: 300px"></div>
  
      <h3>Time from 1st message to 1st Meeting</h1>
      <div id="chartdiv" style="min-height: 300px"></div>
  `)




  let facebook_messages = ()=>{
    let chart = am4core.create("facebook_messages_chart", am4charts.XYChart);
    let data = window.wp_json_object.stats.facebook_messages
    chart.data = data;

    let dateAxis = chart.xAxes.push(new am4charts.DateAxis());
    dateAxis.title.text = "Time";

    dateAxis.renderer.grid.template.location = 0;
    dateAxis.minZoomCount = 5;


    // this makes the data to be grouped
    dateAxis.groupData = true;
    dateAxis.groupCount = 500;

    let valueAxis = chart.yAxes.push(new am4charts.ValueAxis());
    valueAxis.title.text = "Number of contacts"

    let series = chart.series.push(new am4charts.LineSeries());
    series.dataFields.dateX = "date";
    series.dataFields.valueY = "count";
    series.tooltipText = "{valueY}";
    series.tooltip.pointerOrientation = "vertical";
    series.tooltip.background.fillOpacity = 0.5;

    series.connect = false;
    let bullet = series.bullets.push(new am4charts.CircleBullet());
    bullet.stroke = am4core.color("#fff");
    bullet.strokeWidth = 1;

    chart.cursor = new am4charts.XYCursor();
    chart.cursor.xAxis = dateAxis;

    let scrollbarX = new am4core.Scrollbar();
    scrollbarX.marginBottom = 20;
    chart.scrollbarX = scrollbarX;

    chart.events.on("inited", function(ev) {
      dateAxis.zoomToDates(new Date(moment().format('Y')-2, 0), new Date());
    });
  }
  facebook_messages()

  let facebook_contacts = ()=>{
    let chart = am4core.create("facebook_contacts_chart", am4charts.XYChart);
    let data = window.wp_json_object.stats.facebook_contacts
    chart.connect = false;
    chart.data = data;


    let dateAxis = chart.xAxes.push(new am4charts.DateAxis());
    dateAxis.title.text = "Time";

    dateAxis.renderer.grid.template.location = 0;
    dateAxis.minZoomCount = 5;


    // this makes the data to be grouped
    dateAxis.groupData = true;
    dateAxis.groupCount = 500;

    let valueAxis = chart.yAxes.push(new am4charts.ValueAxis());
    valueAxis.title.text = "Number of contacts"

    let series = chart.series.push(new am4charts.LineSeries());
    series.dataFields.dateX = "date";
    series.dataFields.valueY = "count";
    series.tooltipText = "{valueY}";
    series.tooltip.pointerOrientation = "vertical";
    series.tooltip.background.fillOpacity = 0.5;

    series.connect = false;
    let bullet = series.bullets.push(new am4charts.CircleBullet());
    bullet.stroke = am4core.color("#fff");
    bullet.strokeWidth = 1;

    chart.cursor = new am4charts.XYCursor();
    chart.cursor.xAxis = dateAxis;

    let scrollbarX = new am4core.Scrollbar();
    scrollbarX.marginBottom = 20;
    chart.scrollbarX = scrollbarX;

    chart.events.on("inited", function(ev) {
      dateAxis.zoomToDates(new Date(moment().format('Y')-2, 0), new Date());
    });
  }
  facebook_contacts()

  let facebook_assignments = ()=>{
    let chart = am4core.create("facebook_assignments_chart", am4charts.XYChart);
    let data = window.wp_json_object.stats.facebook_assignments

    chart.data = data;


    let dateAxis = chart.xAxes.push(new am4charts.DateAxis());
    dateAxis.title.text = "Time";

    dateAxis.renderer.grid.template.location = 0;
    dateAxis.minZoomCount = 5;


    // this makes the data to be grouped
    dateAxis.groupData = true;
    dateAxis.groupCount = 500;

    let valueAxis = chart.yAxes.push(new am4charts.ValueAxis());
    valueAxis.title.text = "Number of assignments"

    let series = chart.series.push(new am4charts.LineSeries());
    series.dataFields.dateX = "date";
    series.dataFields.valueY = "count";
    series.tooltipText = "{valueY}";
    series.tooltip.pointerOrientation = "vertical";
    series.tooltip.background.fillOpacity = 0.5;

    series.connect = false;
    let bullet = series.bullets.push(new am4charts.CircleBullet());
    bullet.stroke = am4core.color("#fff");
    bullet.strokeWidth = 1;

    chart.cursor = new am4charts.XYCursor();
    chart.cursor.xAxis = dateAxis;

    let scrollbarX = new am4core.Scrollbar();
    scrollbarX.marginBottom = 20;
    chart.scrollbarX = scrollbarX;

    chart.events.on("inited", function(ev) {
      dateAxis.zoomToDates(new Date(moment().format('Y')-2, 0), new Date());
    });
  }
  facebook_assignments()

  let facebook_meetings = ()=>{
    let chart = am4core.create("facebook_meetings_chart", am4charts.XYChart);
    let data = window.wp_json_object.stats.facebook_meetings

    chart.data = data;


    let dateAxis = chart.xAxes.push(new am4charts.DateAxis());
    dateAxis.title.text = "Time";

    dateAxis.renderer.grid.template.location = 0;
    dateAxis.minZoomCount = 5;


    // this makes the data to be grouped
    dateAxis.groupData = true;
    dateAxis.groupCount = 500;

    let valueAxis = chart.yAxes.push(new am4charts.ValueAxis());
    valueAxis.title.text = "Number of meetings"

    let series = chart.series.push(new am4charts.LineSeries());
    series.dataFields.dateX = "date";
    series.dataFields.valueY = "count";
    series.tooltipText = "{valueY}";
    series.tooltip.pointerOrientation = "vertical";
    series.tooltip.background.fillOpacity = 0.5;

    series.connect = false;
    let bullet = series.bullets.push(new am4charts.CircleBullet());
    bullet.stroke = am4core.color("#fff");
    bullet.strokeWidth = 1;

    chart.cursor = new am4charts.XYCursor();
    chart.cursor.xAxis = dateAxis;

    let scrollbarX = new am4core.Scrollbar();
    scrollbarX.marginBottom = 20;
    chart.scrollbarX = scrollbarX;

    chart.events.on("inited", function(ev) {
      dateAxis.zoomToDates(new Date(moment().format('Y')-2, 0), new Date());
    });
  }
  facebook_meetings()

  let time_to_meeting = ()=>{
    am4core.ready(function() {
      // am4core.useTheme(am4themes_animated);

      var chart = am4core.create("chartdiv", am4charts.XYChart);
      chart.paddingRight = 20;

      var data = window.wp_json_object.stats.message_to_meeting
      let highest = _.get(_.last( data ), 'd')
      for ( let i = 0; i<highest; i++){
        if ( !_.find( data, {d:i})){
          data.push({d:i,occ:0})
        }
      }
      data = _.orderBy( data, "d")
      chart.data = data;

      var durationAxis = chart.xAxes.push(new am4charts.DurationAxis());
      durationAxis.title.text = "Days from 1st message to First Meeting Complete";
      durationAxis.baseUnit = "day";
      durationAxis.renderer.grid.template.location = 0;
      durationAxis.minZoomCount = 5;
      durationAxis.durationFormatter.durationFormat = "d'days'";

      // this makes the data to be grouped
      durationAxis.groupData = true;

      var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());
      valueAxis.title.text = "Number of contacts";

      var series = chart.series.push(new am4charts.LineSeries());
      series.dataFields.valueX = "d";
      series.dataFields.valueY = "occ";
      series.tooltipText = "{valueY}";
      series.tooltip.pointerOrientation = "vertical";
      series.tooltip.background.fillOpacity = 0.5;
      series.connect = false;

      chart.cursor = new am4charts.XYCursor();
      chart.cursor.xAxis = durationAxis;

      var scrollbarX = new am4core.Scrollbar();
      scrollbarX.marginBottom = 20;
      chart.scrollbarX = scrollbarX;

      chart.events.on("inited", function(ev) {
        dateAxis.zoomToDates(new Date(moment().format('Y')-2, 0), new Date());
      });
    }); // end am4core.ready()
  }
  time_to_meeting()


})
