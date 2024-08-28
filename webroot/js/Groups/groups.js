$(document).ready(function() {
      $(".fg-toolbar").prepend($('<div id="create_button"></div>'));
      $("#create_button").html('<a href="/Groups/add" title="New Group Mapping" style="margin-top: 10px; margin-left: 5px; float:left; "><img src="/images/plus_black_16px.svg"> <span style="color: #0967b2;text-decoration: underline;"> New Group Mapping</span></a>');
});
