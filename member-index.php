<?php
	require_once('auth.php');
?>
<!DOCTYPE html>
<!--
 --------------------------------------------------------------------------
 Copyright 2012 British Broadcasting Corporation and Vrije Universiteit 
 Amsterdam
 
    Licensed under the Apache License, Version 2.0 (the "License");
    you may not use this file except in compliance with the License.
    You may obtain a copy of the License at
 
        http://www.apache.org/licenses/LICENSE-2.0
 
    Unless required by applicable law or agreed to in writing, software
    distributed under the License is distributed on an "AS IS" BASIS,
    WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
    See the License for the specific language governing permissions and
    limitations under the License.
 --------------------------------------------------------------------------
-->

<html>
  <head>
    <title>N-Screen</title>

    <link type="text/css" rel="stylesheet" href="css/ipad.css" />
    <script type="text/javascript" src="lib/jquery-1.4.4.min.js"></script>
    <script type="text/javascript" src="lib/jquery-ui-1.8.10.custom.min.js"></script>
    <script type="text/javascript" src="lib/jquery.ui.touch.js"></script>

    <script type="text/javascript" src="lib/strophe.js"></script>
    <script type="text/javascript" src="lib/buttons.js"></script>
    <script type="text/javascript" src="lib/spin.min.js"></script>
    <script type="text/javascript" src="lib/play_video.js"></script>

    <script type="text/javascript">

//the main buttons object
var buttons = null;

//if there's more than one TV you'll get more than one message so this is to limit the duplication
var lastmsg="";

//this is so we can link to the TV and perhaps the api easily
var clean_loc = String(window.location).replace(/\#.*/,"");

//group name is based on the part after # in the url, handled in init()
var my_group=null;

//for api calls
//could be a web service
var api_root = "data/";

var recommendations_url=api_root+"recommendations.js"
var channels_url=api_root+"channels.js";
var search_url = api_root+"search.js";

//jabber server
var server = "jabber.notu.be";

//polling interval (for changes to channels)
var interval = null;

//handle group names
//if no group name, show the intro text

function init(){

create_buttons();
var state = {"canBeAnything": true};
  history.pushState(state, "N-Screen", "/N-Screen/");

  var grp = window.location.hash;

   if(grp){
     my_group = grp.substring(1);
     $("#header").show();
     $("#roster_wrapper").show();
     $(".about").hide();
     create_buttons();
   }else{
     my_group=tmp_group();
     $("#header").show();
     $("#roster_wrapper").show();
     $(".about").hide();
     create_buttons();
   }
   $("#group_name").html(my_group);
   $("#grp_link").html(clean_loc+"#"+my_group);
   $("#grp_link").attr("href",clean_loc+"#"+my_group);
   window.location.hash=my_group;
   add_name();

}

// utility function create a temporary group name if none is set
function tmp_group(){
  //var rand = Math.floor(Math.random()*9999);
  var rand = 1;
  return String(rand);
}

//creates and initialises the buttons object                              

function create_buttons(){
   $("#inner").addClass("inner_noscroll");
   $(".slidey").addClass("slidey_noscroll");
   $(".about").hide();
   $("#header").show();
   $("#roster_wrapper").show();
     
   //ask the user for their name to continue
   show_ask_for_name();
    
   //set up notifications area
   $("#notify").toggle(
     function (){
       //console.log("SHOW");
       $("#notify_large").show();
     },
     function (){
       //console.log("HIDE");
       $("#notify_large").hide();
       $("#notify").html("");
       $("#notify_large").html("");
       $("#notify").hide();
     }
   );

   //initialise buttons object and start the link
   buttons = new ButtonsLink({"server":server});

}

//called when buttons link is created

function blink_callback(blink){
  get_channels();
  var delay = 60000;

  interval = setInterval(get_channels, delay);

  get_roster(blink);

  $(document).trigger('refresh');
  $(document).trigger('refresh_buttons');
  $(document).trigger('refresh_group');
  $(document).trigger('refresh_history');
  $(document).trigger('refresh_recs');
  $(document).trigger('refresh_search');
}


//get the initial channels
//calls retrieve_channels
function get_channels(){
console.log("channels_url");
console.log(channels_url);
    $.ajax({
      url: channels_url,
      dataType: "json",
      success: function(data){
         retrieve_channels(data);
      },
      error: function(jqXHR, textStatus, errorThrown){
      //console.log("nok "+textStatus);
      }

    });


}


//do something with the initial channels information
//in this case, print them out

function retrieve_channels(result){
  var html = [];
  var suggestions = result["suggestions"];
  for(var r in suggestions){
                  var id = suggestions[r]["id"];
                  if(!id){
                    id = suggestions[r]["pid"];
                  }
                  //in this case (since these are channels) for top level, don't make draggable
                  suggestions[r]["classes"]="ui-widget-content button nondrag_programme open_win";
                  html.push(generate_html_for_programme(suggestions[r],false,id,false).join("\n"));    
  }

  //our special channels

  var hh = {"id":"hist","pid":"hist", "title":"Recently Viewed", "description":"Programmes you've looked at.","image":"images/history_wide.png","classes":"ui-widget-content nondrag_programme snaptarget_history"};

  var gg = {"id":"grp","pid":"grp","title":"Shared by friends","description":"Shared by friends","image":"images/group_wide.png","classes":"ui-widget-content button nondrag_programme"};

  var rr = {"id":"recos","pid":"recos","title":"Recommendations for you","description":"Recommendations for you.","image":"images/recs_wide.png","classes":"ui-widget-content button nondrag_programme snaptarget_recs"};

  var se = {"id":"se","pid":"se","title":"Search Results","description":"Results of last search","image":"images/search_results.png","classes":"ui-widget-content button nondrag_programme snaptarget_search"};

  html.push(generate_html_for_programme(gg,false,"grp").join("\n"));
  html.push(generate_html_for_programme(rr,false,"recos").join("\n"));
  html.push(generate_html_for_programme(hh,false,"hist").join("\n"));
  html.push(generate_html_for_programme(se,false,"se").join("\n"));


  $("#progs").html(html.join("\n"));

  //make sure all the drag and drop works
  $(document).trigger('refresh');
  $(document).trigger('refresh_buttons');
  $(document).trigger('refresh_group');
  $(document).trigger('refresh_history');
  $(document).trigger('refresh_recs');
  $(document).trigger('refresh_search');
}



//ask the user for their name

function show_ask_for_name(){

   $("#devices_menu").hide();
  
   $('#ask_name').show();
   show_grey_bg();
   $("#login").focus();
  
}

//when the user enters their name, tell buttons

function add_name(){
  var name = "<?php echo $_SESSION['SESS_FIRST_NAME'];?>";
  if(name){

    var me = new Person(name,name);
    buttons.me = me;
    $(document).trigger('send_name');
    $("#ask_name").hide();
    $("#bg").hide();

//get some 'personalised recommendations' 

    $.ajax({
      url: recommendations_url,
      dataType: "json",
      success: function(data){
        recommendations(data,"recs_items");
      },
      error: function(jqXHR, textStatus, errorThrown){
        console.log("!!nok "+textStatus);
      }

    });
   
  }
  var state = {"canBeAnything": true};
  history.pushState(state, "N-Screen", "/N-Screen/");
  window.location.hash=my_group;
  $("#logoutspan").show();
}


// various triggered things

// Connect to the service as me

$(document).bind('send_name', function () {
  console.log("sending name and connecting "+buttons.me.name);
  buttons.connect(buttons.me,my_group,false); // third arg is debugging
});


//when connection is confirmed
$(document).bind('connected', function (ev,blink) {
  //get the initial stuff
  blink_callback(blink);
});

//what to do when disconnected

$(document).bind('disconnected',function(){
   console.log("disconnecting");
   if(interval){
     clearInterval(interval);//stop polling
   }
   $('#disconnected').show();
   show_grey_bg();
   $("#login").focus();

});

//when the group changes, update the roster

$(document).bind('items_changed',function(ev,blink){
    get_roster(blink);
    $(document).trigger('refresh');
    $(document).trigger('refresh_buttons');
    $(document).trigger('refresh_group');
    $(document).trigger('refresh_history');
    $(document).trigger('refresh_recs');
    $(document).trigger('refresh_search');
});

//when someone shares something, put a copy of it in the right place

$(document).bind('shared_changed', function (e,programme,name,msg_type) {
  var a = get_object("a2");
  a.play();

  var id = generate_new_id(programme,name);

  var msg_text = "";
  var html = null

  if(programme.item_type=="webpage"){
    html = generate_html_for_webpage(programme,name,id);
    msg_text = name+" shared <a onclick='show_webpage(\""+programme["link"]+")'>"+programme["title"]+"</a> with you";
    if(msg_type=="groupchat"){
      msg_text = name+" shared <a onclick='show_webpage(\""+programme["link"]+")'>"+programme["title"]+"</a> with the group";
    }
  }else{
    if(programme.item_type=="video"){
      html = generate_html_for_video(programme,name,id);
      msg_text = name+" shared <a onclick='show_video(\""+programme["link"]+"\")'>"+programme["title"]+"</a> with you";
      if(msg_type=="groupchat"){
        msg_text = name+" shared <a onclick='show_video(\""+programme["link"]+"\")'>"+programme["title"]+"</a> with the group";
      }
    }else{
      html = generate_html_for_programme(programme,name,id);
      msg_text = name+" shared "+programme["title"]+" with you";
      if(msg_type=="groupchat"){
        msg_text = name+" shared "+programme["title"]+" with the group";
      }
    }
  }

  $('#shared').append(html.join(''));

//notifications 
  build_notification(msg_text,programme,name);

  $(document).trigger('refresh_group');
  $(document).trigger('refresh_buttons');
  $(document).trigger('refresh_history');
  $(document).trigger('refresh_recs');
  $(document).trigger('refresh_search');

});

//we can play some video locally

function show_video(mp4){

      $('#new_overlay').html("<video id='myvid' width=\"100%\" controls autoplay><source src=\""+mp4+"\"></video>");
      $('.new_overlay').hide();
      $('#new_overlay').show();
      show_grey_bg();

}

//webpage anntations reult in a new window

function show_webpage(url){
        window.open(url);
}


//when the TV changes, print out what's being watched

$(document).bind('tv_changed', function (ev,item) {
  var ct,cid;
  var ot = item.obj_type;
  var id = "tv";
  if(ot=="tv"){
      ct = item.nowp["title"];
      cid = item.nowp["id"];
  }
  var pid = item.nowp["pid"];

  $("#tv").find(".dotted_spacer").html(ct)
  $("#tv").attr("pid",pid);

//notifications

  $("#tv").find(".dotted_spacer").html(item.nowp["title"]);
  
  var msg_text = "TV started playing "+item.nowp["title"];
  if(item["nowp"]["state"]=="pause"){
    msg_text = "TV paused "+item.nowp["title"];
  }
  build_notification(msg_text,item.nowp, item.name);

});



//html interactions

//create the notifications

function build_notification(msg_text,programme,name){

  if(lastmsg!=msg_text){
    var p = $("#notify").text();
    var num = parseInt(p);

    if(!num){
      num=1;
    }else{
      num = num+1;
    }

    lastmsg = msg_text;
    var nid = generate_new_id(programme,name)+"_notification";
    $("#notify").html(num);
    $("#notify").show();
    $("#notify_large").prepend("<div id='"+nid+"' class='dotty_bottom'>"+msg_text+" </div>");//not sure if append / prepend makes most sense

  }            
}


//print out who is in the group and what sort of thing they are

function get_roster(blink){
  var roster = blink.look();

  if(roster["me"]){
    $("#title").html("hi, "+roster["me"].name+", here's what's on <a href='player.html#"+my_group+"' target='_blank'>TV</a>");
  }
  $("#roster").empty();

  var html=[];

  if(roster){

    html.push("<h3 class=\"contrast\">SHARE WITH</h3>");

    html.push("<div class='snaptarget_group person' id='group'>");
    html.push("<img class='img_person' src='images/group.png'  />");
    html.push("<div class='friend_name' id='grp'>Group #"+my_group+"</div>");
    html.push("</div>");

    html.push("<br clear=\"both\" />");

    for(r in roster){
      item = roster[r];

       //i.e. not me
      if(item && item.name!=buttons.me.name){

        // if a person
        if(item.obj_type=="person"){
          html.push("<div class='snaptarget person' id='"+item.name+"'>");
          html.push("<img class='img_person' src='images/person.png'  />");
          html.push("<div class='friend_name'>"+item.name+"</div>");
          html.push("</div>");
          html.push("<br clear=\"both\" />");
        }

        // if a bot
        if(item.obj_type=="bot"){
          html.push("<div class='snaptarget_bot person' id='"+item.name+"'>");
          html.push("<img class='img_person' src='images/bot.png'  />");
          html.push("<div class='friend_name'>"+item.name+"</div>");
          html.push("</div>");
          html.push("<br clear=\"both\" />");
        }

        // if a TV

        if(item && item.obj_type=="tv"){
            var html_tv = [];
            html_tv.push("<div class='snaptarget_tv telly' id='tv_title'>");
            
            html_tv.push("<div id='tv_name' style='float:right;font-size:16px;padding-top:10px;padding-right:40px;'>My TV</div>");
            html_tv.push("<div style='float:left'><img class='img_tv' src='images/tiny_tv.png' /></div>");
            
            html_tv.push("<br clear=\"both\" />");
    
            html_tv.push("<div class='dotted_spacer'>");
            var nowp = item.nowp;
            if(nowp && nowp["title"]){
              html_tv.push(nowp["title"]);
              $("#tv").attr("pid",nowp["pid"]);
            }else{
              html_tv.push("Nothing currently playing");
              $("#tv").attr("pid","");
            }
            html_tv.push("</div>");
            html_tv.push("</div>");
            html_tv.push("<br clear=\"both\"></br>");
            $('#tv').html(html_tv.join(''));
            $("#tv").unbind('click');
            $("#tv").click(function() {
               var pid = $("#tv").attr("pid");
               if(pid && pid!=""){
                 insert_suggest_from_prog_id(pid,true);
               }
            }).addTouch();

         }
        }
      }

    }
    $('#roster').html(html.join(''));

}


//find some related stuff and show it to the user

function insert_suggest_from_prog_id(id,manifest,get_more){
      $.ajax({
       url: manifest,
       dataType: "json",
         success: function(data){
           insert_suggest(data,get_more);
         },
         error: function(jqXHR, textStatus, errorThrown){
           alert("oh dear "+textStatus);
         }
      });
}



function insert_suggest_from_div(id,get_more){
  var div = $("#"+id);
  var j = get_data_from_programme_html(div);
  insert_suggest(j,get_more);
}

//test if we can play locally

function test_for_playability(formats, provider){
  //this could be handled better
  //might want also to exclude some providers
  if(navigator.platform.indexOf("iPad") != -1 || navigator.platform.indexOf("iPhone") !=-1){
    if(formats["mp4"]){
      return true;
    }else{
      return false;
    }
  }else{
    return true;
  }
}


//display suggestions based on id

function insert_suggest(j,get_more) {
      var id = j["id"];
      var pid = j["pid"];
      var more = j["more"];
      console.log("mm");
      console.log(j);
      console.log("mm");
      html = generate_html_for_programme(j,null,id);

      $('#history_items').prepend(html.join(''));

      html2 = generate_large_html_for_programme(j,"",id);//---
      $('#new_overlay').html(html2.join(''));

      $('.new_overlay').hide();
      $('#new_overlay').show();
      show_grey_bg();

      if(more && more!="undefined" && get_more){

        $('#new_overlay').append("<div class='dotted_spacer2'></div><span class=\"sub_title\">MORE LIKE THIS</span>");

        $('#new_overlay').append("<br clear=\"both\"/>");
        $('#new_overlay').append("<div id='spinner'></div>");
        $('#new_overlay').append("<div id='more'></div>");
        var target = document.getElementById('spinner');//??
        var spinner = new Spinner(opts).spin(target);
        console.log("related "+more);
        $.ajax({
         url: more,
         dataType: "json",
           success: function(data){
             recommendations(data,"more");
           },
           error: function(jqXHR, textStatus, errorThrown){
           //alert("oh dear "+textStatus);
           }
        });
      }

//finally because this can be slow, see aboutplayability
  if(j["manifest"]){
    var manifest = j["manifest"];

      $.ajax({
       url: j["manifest"],
       dataType: "json",
         success: function(data){
           set_playable(data);
         },
         error: function(jqXHR, textStatus, errorThrown){
           alert("oh dear "+textStatus);
         }
      });
  }
}


function set_playable(manifest_data){

    if(manifest_data && manifest_data["limo"]){
       //two kinds of manifest - one with events and one not
       // this is the events one

       if(manifest_data["limo"]["event-resources"][0]["link"]){

         $.ajax({
           url: manifest_data["limo"]["event-resources"][0]["link"],
           dataType: "json",
           success: function(data){
           process_events(data);
           },
           error: function(jqXHR, textStatus, errorThrown){
           console.log("oh dear2 "+textStatus);
           }
         });

       }

       if(manifest_data["limo"]["media-resources"][0]["link"]){

        $.ajax({
         url: manifest_data["limo"]["media-resources"][0]["link"],
         dataType: "json",
           success: function(data){
             set_playable(data);
           },
           error: function(jqXHR, textStatus, errorThrown){
             alert("oh dear "+textStatus);
           }
        });
          
       }else{
         console.log("broken manifest limo file");
       }

    }else{

      var locally_playable = false;

      if(manifest_data && manifest_data["media"]){
         var swf = manifest_data["media"]["swf"];
         var mp4 = manifest_data["media"]["mp4"];

         var provider = manifest_data["provider"];
         var formats = {"swf":swf,"mp4":mp4};
         locally_playable = test_for_playability(formats, provider);
         if(locally_playable){
           $(".play_button").show();
           $(".play_button").unbind('click');
           $( ".play_button" ).click(function() {
                      //get the prpgramme
                      var el = $( this ).parent().parent();
                      var programme = get_data_from_programme_html(el);

                      $("#new_overlay").html("<div class='close_button'><img src='images/close.png' width='30px' onclick='javascript:hide_overlay();'/></div><div id='player'></div>");
                      process_video(programme,formats,provider);
                      return false;

           });


         }
      }
   }
}

//recommendations
//called when recommendations are returned

function recommendations(result,el){

   if(result){
          var suggestions = result["suggestions"];
          var got_ids = {};
          if(!suggestions){
            suggestions = result["results"];
          }
          var pid_title = result["title"];
          if(suggestions.length==0){
            console.log("no suggestions");
          }else{

            //order according to number
            suggestions.sort(sortfunction)
            $("#spinner").empty();

            //print
            var html = [];
            for(var r in suggestions){
                  var id = suggestions[r]["id"];
                  if(!id){
                    id = suggestions[r]["pid"];
                  }
                  if(!got_ids[id]){
                    html.push(generate_html_for_programme(suggestions[r],false,id).join("\n"));    
                  }
                  got_ids[id]=id;
            }

            $("#"+el).html(html.join("\n"));

            //make sure all the drag and drop works
            $(document).trigger('refresh');
            $(document).trigger('refresh_buttons');
            $(document).trigger('refresh_group');
            $(document).trigger('refresh_history');
            $(document).trigger('refresh_recs');
            $(document).trigger('refresh_search');

          }
    }else{

     console.log("OOPS!");

    }
}


//sorting function - sort by number
function sortfunction(a, b){
  //Compare "a" and "b" in some fashion, and return -1, 0, or 1
  var n1 = a["number"];
  var n2 = b["number"];
  return (n2 - n1);
}

//spinner stuff

//http://fgnass.github.com/spin.js/
var opts = {
  lines: 12, // The number of lines to draw
  length: 5, // The length of each line
  width: 3, // The line thickness
  radius: 5, // The radius of the inner circle
  color: '#fff', // #rbg or #rrggbb
  speed: 1, // Rounds per second
  trail: 100, // Afterglow percentage
  shadow: true // Whether to render a shadow
};


//ensure the drag and drop is working

$(document).bind('refresh', function () {
                $( "#draggable" ).draggable();
                $( ".programme" ).draggable(
                        {
                        opacity: 0.7,
                        helper: "clone",
                        zIndex: 2700,
			start: function() {
                          $(".snaptarget").addClass( "dd_highlight"); 
                          $(".snaptarget_tv").addClass( "dd_highlight"); 
                          $(".snaptarget_group").addClass( "dd_highlight"); 
                          $(".snaptarget_bot").addClass( "dd_highlight"); 
			},
			drag: function() {
                          $(".snaptarget").addClass( "dd_highlight"); 
                          $(".snaptarget_tv").addClass( "dd_highlight"); 
                          $(".snaptarget_group").addClass( "dd_highlight"); 
                          $(".snaptarget_bot").addClass( "dd_highlight"); 
			},
			stop: function() {
                          $(".snaptarget").removeClass( "dd_highlight"); 
                          $(".snaptarget_tv").removeClass( "dd_highlight"); 
                          $(".snaptarget_group").removeClass( "dd_highlight"); 
                          $(".snaptarget_bot").removeClass( "dd_highlight"); 
			}

                }).addTouch();
                $( ".large_prog" ).draggable(
                        {
                        opacity: 0.7,
                        helper: "clone",
                        zIndex: 2700
                }).addTouch();

                $( ".snaptarget" ).droppable({
           
                        hoverClass: "dd_highlight_dark",
                        drop: function(event, ui) {
     
                                var el = $(this);
                                var jid = el.attr('id');
                                var el3 = ui.helper;
                                var el2 = el3.parent();

                                var a = get_object("a1");
                                a.play();

                                var res = get_data_from_programme_html(el3);//??
                                var url = el3.attr('href');
                                buttons.share(res,new Person(jid,jid));

                                $( this ).addClass( "dd_highlight",10,function() {
                                        setTimeout(function() {
                                                el.removeClass( "dd_highlight" ,100);
                                        }, 1500 );

                                });
                        }

                }).addTouch();
                $( ".snaptarget_group" ).droppable({
           
                        hoverClass: "dd_highlight_dark",
                        drop: function(event, ui) {
     
                                var el = $(this);
                                var jid = el.attr('id');
 
                                var el3 = ui.helper;
                                var el2 = el3.parent();

                                var a = get_object("a1");
                                a.play();

                                var res = get_data_from_programme_html(el3);//??
                                var url = el3.attr('href');
                                buttons.share(res);

                                $( this ).addClass( "dd_highlight",10,function() {
                                        setTimeout(function() {
                                                el.removeClass( "dd_highlight" ,100);
                                        }, 1500 );

                                });
                        }

                }).addTouch();


                $( ".snaptarget_bot" ).droppable({
           
                        hoverClass: "dd_highlight_dark",
                        drop: function(event, ui) {
     
                                var el = $(this);
                                var jid = el.attr('id');
 
                                var el3 = ui.helper;
                                var el2 = el3.parent();

                                var a = get_object("a1");
                                a.play();
                                var res = get_data_from_programme_html(el3);//??


                                html3 = [];
                                html3.push("<div id=\""+res["id"]+"_favs\" pid=\""+res["pid"]+"\" href=\""+recs["video"]+"\" class=\"ui-widget-content button programme ui-draggable open_win\">");
                                html3.push("<img class=\"img\" src=\""+res["image"]+"\" />");
                                html3.push("<span class=\"p_title\">"+res["title"]+"</a>");
                                html3.push("<p class=\"description large\">"+res["description"]+"</b></p>");
                                html3.push("</div>");
                                $('#favs').prepend(html3.join(''));
                                buttons.share(res,new Person(jid,jid))

                                $( this ).addClass( "dd_highlight",10,function() {
                                        setTimeout(function() {
                                                el.removeClass( "dd_highlight" ,100);
                                        }, 1500 );

                                });
                        }

                }).addTouch();

                $( ".snaptarget_tv" ).droppable({  //for tvs

                        hoverClass: "dd_highlight_dark",
                        drop: function(event, ui) {

                                var el = $(this);
                                var jid = el.attr('id');
                         
                                var el3 = ui.helper;
                                var el2 = el3.parent();

                                var a = get_object("a1");
                                a.play();

                                var res = get_data_from_programme_html(el3);//??
                                res["action"]="play";
                                res["shared_by"] = buttons.me.name;
                                var url = el3.attr('href');
                                var name = jid;
//go throgh the roster and send to all tvs
                                share_to_tvs(res);
                                $( this ).addClass( "dd_highlight",10,function() {
                                        setTimeout(function() {
                                                el.removeClass( "dd_highlight" ,100);
                                
                                        }, 1500 );
                                
                                });
                        }
                                
                }).addTouch();

});


function share_to_tvs(res){
////hm this should be an ajax call
                                var roster = buttons.blink.look();
                                if(roster){
                                  for(r in roster){
                                    var item = roster[r];
                                    if(item.obj_type =="tv"){
                                      var nm = item.name;
                                      buttons.share(res, new TV(nm,nm));//need to send this to a list of tvs

                                    }
                                  }
                                }

}



$(document).bind('refresh_group', function () {

                $(".snaptarget_group").unbind('click');
                $( ".snaptarget_group" ).click(function() {

                        $('.new_overlay').hide();
//open a new overlay containing group shared
                        $('#results').addClass("new_overlay");
                        $('#results').show();
                        show_grey_bg();
                        return false;

                });

                $("#grp").unbind('click');
                $( "#grp" ).click(function() {

                        $('.new_overlay').hide();
//open a new overlay containing group shared
                        $('#results').addClass("new_overlay");
                        $('#results').show();
                        show_grey_bg();
                        return false;

                });

});


//adds an overlay and inserts related stuff

$(document).bind('refresh_buttons', function () {

                $(".open_win").unbind('click');
                $( ".open_win" ).click(function() {
                        insert_suggest_from_div($( this ).attr("id"),true);//??
                        return false;

                });

//for adverts / video
                $(".open_vid_win").unbind('click');
                $( ".open_vid_win" ).click(function() {
                        //show_video($( this ).attr("href"));
                        insert_suggest_from_div($( this ).attr("id"),true);//??

                        return false;

                });
//for webpages
                $(".open_win_web").unbind('click');
                $( ".open_win_web" ).click(function() {
                        show_webpage($( this ).attr("href"));
                        return false;

                });


});


$(document).bind('refresh_history', function () {

                $(".snaptarget_history").unbind('click');
                $( ".snaptarget_history" ).click(function() {

//open a new overlay containing history

//apply class here
                        $('.new_overlay').hide();
                        $('#history').addClass("new_overlay");

                        $('#history').show();
                        show_grey_bg();
                        return false;

                });

});


$(document).bind('refresh_recs', function () {

                $(".snaptarget_recs").unbind('click');
                $( ".snaptarget_recs" ).click(function() {
//open a new overlay containing recs

//apply class here
                        $('.new_overlay').hide();
                        $('#recs').addClass("new_overlay");

                        $('#recs').show();
                        show_grey_bg();
                        return false;

                });

});


$(document).bind('refresh_search', function () {

                $(".snaptarget_search").unbind('click');
                $( ".snaptarget_search" ).click(function() {
//open a new overlay containing search

//apply class here
                        $('.new_overlay').hide();
                        $('#ssee').addClass("new_overlay");

                        $('#ssee').show();
                        show_grey_bg();
                        return false;

                });

});


///touch events stuff for ipad etc

$.extend($.support, {
        touch: "ontouchend" in document
});
                

// Hook up touch events
$.fn.addTouch = function() {
        if ($.support.touch) {
                this.each(function(i,el){
                        el.addEventListener("touchstart", iPadTouchHandler, false);
                        el.addEventListener("touchmove", iPadTouchHandler, false);
                        el.addEventListener("touchend", iPadTouchHandler, false);
                        el.addEventListener("touchcancel", iPadTouchHandler, false);
                });
        }
};


    
//serialising form html to a programme json and vice versa

function generate_large_html_for_programme(j,n,id){
      var pid=j["pid"];
      var video = j["video"];
      var title=j["title"];
      var link=j["url"];
      var img=j["image"];
      var more=j["more"];
      var action=j["action"];
      var state=j["state"];
      var manifest=j["manifest"];
      var service=j["service"];
      var is_live=j["is_live"];
      var desc=j["description"];
      var explanation=j["explanation"];

      html2 = [];

      html2.push("<div class='close_button'><img src='images/close.png' width='30px' onclick='javascript:hide_overlay();'/></div>");
      html2.push("<div id=\""+id+"_overlay\" pid=\""+pid+"\" class=\"ui-widget-content large_prog ui-draggable button "+pid+"\" ");
      if(more){
        html2.push(" more=\""+more+"\"");
      }
      html2.push(" is_live=\""+is_live+"\"");

      if(video){
        html2.push("  href=\""+video+"\"");
      }
      if(service){
        html2.push("  service=\""+service+"\"");
      }
      if(manifest){
        html2.push("  manifest=\""+manifest+"\"");
      }
      html2.push(">");

      html2.push("<div style='float:left;'> <img class=\"img\" src=\""+img+"\" />");
      html2.push("<div class=\"play_button\"><img src=\"images/play.png\"></div>");
      html2.push("<div style='width:300px;float:left;padding-left:10px;'>");
      html2.push("<div class=\"p_title_large\">"+title+"</div>");
//    html2.push("<p class=\"shared_by\">"+n+"</p>");



      if(desc){
        html2.push("<p class=\"description\">"+desc+"</p>");
      }
      if(explanation){
        html2.push("<p class=\"explain\">"+explanation+"</p>");
      }

      if(link){
        html2.push("<p class=\"link\"><a href=\""+link+"\" target=\"_blank\">Link</a></p>");
      }
      html2.push("</div></div>");
      html2.push("<br clear=\"both\"/>");
      html2.push("</div>");
      //alert("na "+j["action"]);
      //$(document).trigger("fix_control_button",[j,state]);

      return html2;


}

//create html from a programme
function generate_html_for_programme(j,n,id){

      var pid=j["pid"];
      var video = j["video"];
      var title=j["title"];
      if(!title){
         title = j["core_title"];
      }
      var img=j["image"];
      if(!img){
        img=j["depiction"];
      }
      var manifest=j["manifest"];
      var more=j["more"];
      var explanation=j["explanation"];
      var desc=j["description"];
      var classes= j["classes"];
      var is_live = false;
      if(j["live"]==true || j["live"]=="true" || j["is_live"]==true || j["is_live"]=="true"){
        is_live = true;
      }
      var service = j["service"];
      var channel = j["channel"];
      if(channel && is_live){
        img = "channel_images/"+channel.replace(" ","_")+".png";
      }


      var html = [];
      html.push("<div id=\""+id+"\" pid=\""+pid+"\"");
      if(more){
        html.push(" more=\""+more+"\"");
      }
      html.push(" is_live=\""+is_live+"\"");

      if(video){
        html.push("  href=\""+video+"\"");
      }
      if(service){
        html.push("  service=\""+service+"\"");
      }
      if(manifest){
        html.push("  manifest=\""+manifest+"\"");
      }
      if(classes){
        html.push("class=\""+classes+"\">");
      }else{
        html.push("class=\"ui-widget-content button programme open_win\">");
      }
      html.push("<div class='img_container'><img class=\"img\" src=\""+img+"\" />");
      html.push("</div>");
      if(is_live){
       html.push("Live: ");

      }else{
      }
      html.push("<span class=\"p_title p_title_small\">"+title+"</span>");
      html.push("<div clear=\"both\"></div>");
      if(n){                    
        html.push("<span class=\"shared_by\">Shared by "+n+"</span>");
      }
      if(desc){
        html.push("<span class=\"description large\">"+desc+"</span>");
      }
/*
      if(explanation){

        //string.charAt(0).toUpperCase() + string.slice(1);
        explanation = explanation.replace(/_/g," ");
        var exp = explanation.replace(/,/g," and ");

        html.push("<span class=\"explain_small\">Matches "+exp+" in your profile</span>");
      }
*/
      html.push("</div>");
      return html
}    


///webpages - this is early stuff
function generate_html_for_webpage(j,n,id){

      var title=j["title"];
      var link=j["link"];
      var classes = null;
      var html = [];

      html.push("<div id=\""+id+"\" pid=\""+id+"\" href=\""+link+"\" ");
      if(classes){
        html.push("class=\""+classes+"\">");
      }else{
        html.push("class=\"ui-widget-content button programme open_win_web\">");
      }
      var img = "images/webpage.png";
//      html.push("<div><a href='"+link+"' target='_blank'><img class=\"img\" src=\""+img+"\" /></a></div>");
      html.push("<div><img class=\"img\" src=\""+img+"\" /></div>");
      html.push("<span class=\"p_title p_title_small\"><a href='"+link+"' target='_blank'>"+title+"</a></span>");
      html.push("<div clear=\"both\"></div>");
      if(n){                    
        html.push("<span class=\"shared_by\">Shared by "+n+"</span>");
      }
      html.push("</div>");
      return html
}    


//video on the client - even earlier stuff
function generate_html_for_video(j,n,id){

      var title=j["title"];
      var link=j["link"];
      var classes = null;
      var html = [];

      html.push("<div id=\""+id+"\" pid=\""+id+"\" href=\""+link+"\"");
      if(classes){
        html.push("class=\""+classes+"\">");
      }else{
        html.push("class=\"ui-widget-content button programme open_vid_win\">");
      }
      var img = "images/video.png";
      html.push("<div><img class=\"img\" src=\""+img+"\" /></div>");

      html.push("<span class=\"p_title p_title_small\">"+title+"</span>");
      html.push("<div clear=\"both\"></div>");
      if(n){                    
        html.push("<span class=\"shared_by\">Shared by "+n+"</span>");
      }
      html.push("</div>");
      return html
}    


//from a programme html element, get the json

function get_data_from_programme_html(el){
     var item_type = "programme";
     var id = el.attr('id');
     var pid = el.attr('pid');
     var video = el.attr('href');
     var more = el.attr('more');
     var service = el.attr('service');
     var is_live = el.attr('is_live');
     var manifest = el.attr('manifest');
     var img = el.find("img").attr('src');
     var title=el.find(".p_title").text();
     if(!title){
        title=el.find(".p_title_large").text();
     }
     var desc=el.find(".description").text();
     var explain=el.find(".explain").text();
                                                
     var res = {};                 
     res["id"]=id;
     res["pid"]=pid;
     res["video"]=video;
     res["image"]=img;
     res["title"]=title; 
     res["more"]=more; 
     res["service"]=service; 
     res["item_type"]=item_type; 
     res["is_live"]=is_live; 

     res["manifest"]=manifest; 
     res["description"]=desc;
     res["explanation"]=explain;
     return res;
                                
}


//a few utility functions

//search stuff

function remove_search_text(){
  $("#search_text").attr("value","");
}

function do_search(txt){

  txt = txt.toLowerCase();

  $("#ssee").find(".description").html("Search for "+txt);
  $.ajax({
    url: search_url,
    dataType: "json",
    success: function(data){
      search_results(data,txt);
    },
    error: function(jqXHR, textStatus, errorThrown){
    //console.log("nok "+textStatus);
    }

  });

}

function search_results(result,current_query){
  var html = [];
  var suggestions = result;
  for(var r in suggestions){
                  var id = suggestions[r]["id"];
                  if(!id){
                    id = suggestions[r]["pid"];
                  }
                  html.push(generate_html_for_programme(suggestions[r],false,id).join("\n"));
  }

  $("#search_items").html(html.join("\n"));
                        $('.new_overlay').hide();
                        $('#ssee').addClass("new_overlay");

                        $('#ssee').show();
                        show_grey_bg();


  //make sure all the drag and drop works
  $(document).trigger('refresh');
  $(document).trigger('refresh_buttons');
  $(document).trigger('refresh_group');
  $(document).trigger('refresh_search');

}


function get_search_url(q){
  var url = buttons.blink.library()["search"].url+""+q;
  return url;
}


//hides the large notifications bar
function close_notifications(){
  $("#notify_large").hide();
}

//shows the overlay background      
function show_grey_bg(){
 $("#bg").show();       
}

//hides the overlay background
function hide_overlay(){
 $("#bg").hide();
 $(".new_overlay").hide();
 $("#new_overlay").html("");

}

//creates a new id from a programme and a person name string
function generate_new_id(j,n){
  var i = j["pid"]+"_"+n; //not really unique enough
  return i;
}


//annoying bloody audio stuff
//http://codingrecipes.com/documentgetelementbyid-on-all-browsers-cross-browser-getelementbyid
function get_object(id) {
   var object = null;
   if (document.layers) {       
    object = document.layers[id];
   } else if (document.all) {
    object = document.all[id];
   } else if (document.getElementById) {
    object = document.getElementById(id);
   }
   return object;
}




</script>

</head>

<body onload="init()">

<!--REGISTRATION-FORM-->

<?php
  if( isset($_SESSION['ERRMSG_ARR']) && is_array($_SESSION['ERRMSG_ARR']) && count($_SESSION['ERRMSG_ARR']) >0 ) {
    echo '<ul class="err">';
    foreach($_SESSION['ERRMSG_ARR'] as $msg) {
      echo '<li>',$msg,'</li>'; 
    }
    echo '</ul>';
    unset($_SESSION['ERRMSG_ARR']);
  }
?>

<div id="everything" ontouchmove="touchMove(event);">

<!-- workaround for audio problems on ipad - http://stackoverflow.com/questions/2894230/fake-user-initiated-audio-tag-on-ipad -->

<div id="junk" style="display:none">
  <audio src="sounds/x4.wav" id="a1" preload="auto"  autobuffer="autobuffer" controls="" ></audio>
  <audio src="sounds/x1.wav" id="a2" preload="auto"  autobuffer="autobuffer" controls="" ></audio>
</div>


<div class="about" style="display:none;">

This is <a 
href="http://notube.tv/2011/10/10/n-screen-a-second-screen-application-for-small-group-exploration-of-on-demand-content/">N-Screen</a>, 
an application that helps you choose what to watch on TV. It was created by <a 
href="http://twitter.com/libbymiller">Libby Miller</a>, <a 
href="http://twitter.com/vickybuser">Vicky Buser</a> and <a 
href="http://twitter.com/danbri">Dan Brickley</a> within the <a 
href="http://notube.tv">NoTube Project</a>. Many thanks to <a 
href="http://dannyayers.com/">Danny Ayers</a> for the sounds, <a 
href="http://www.sparkslabs.com/michael/">Michael Sparks</a> for the channel icons, and <a 
href="http://www.fabrique.nl/">Fabrique</a> for the designs. <br /> <br /> Thanks also to <a 
href="http://www.cs.vu.nl/~laroyo/">Lora Aroyo</a> and <a 
href="http://www.cs.vu.nl/~guus/">Guus Schreiber</a> and the rest of the NoTube Project, to 
Andrew McParland at the BBC and to <a href="http://twitter.com/nevali">Mo McRoberts</a> from 
the internet. <br /> <br />


<a onclick="create_buttons()">Try it</a>
</div>



  <div id="header" style="display:none;">
   <span id='title'></span>
    <span class="form" >
      <form onsubmit='javascript:do_search(this.search_text.value);return false;'>

        <input type="text" id="search_text" name="search_text" value="search programmes" onclick="javascript:remove_search_text();return false;"/>

      </form>
     </span>
      
  </div>

<br clear="both"/>

        

  
<div id="container">
              

  <div id="inner">
 
        
    <div id="browser">  
      <div id="top_slider" class="slidey">

      <br clear="both"/>

        <div id="progs"></div>


      </div>
 
    </div>
  
    <div id="search_results">
        <span id="sub_title_sr"></span>
        <div id="progs2"></div>

    </div>

    <div id="devices_menu" class="device_menu alert" style="display:none;">
    </div>

              

    <br clear="both" />
 
  </div>
    
</div>
        
<div id="roster_wrapper" style="display:none;">
<div id="aboutlink">
<a target="_blank" href="about.html">About N-Screen</a>
</div>

  <div class="notifications_red" id="notify"></div>
  <div class="notifications_red_large" id="notify_large" onclick="javascript:close_notifications();"></div>

  <div id="roster_inner">
    <div id="tv"></div>
      <br clear="both"/>
    <div id="roster"></div>
  </div>
</div>

        
          
        
<p style="display: none;"><small>Status:
<span id="demo">
<span id="out"></span>
</span></small></p>
      
<!-- overlays -->
  
<div class='new_overlay' id='new_overlay' style='display:none;'><div class='close_button'><img src='images/close.png'/></div></div>
<div id='bg' style='display:none;' onclick='javascript:hide_overlay()'></div>
    

              
            <div id="ask_name" style="display:none;" class="alert">
              <h2 id="inline1_sub">Registration</h2>
              <form id="loginForm" name="loginForm" method="post" action="register-exec.php">
                 First Name<input name="fname" type="text" class="textfield" id="fname" />
                 Last Name<input name="lname" type="text" class="textfield" id="lname" />
                 Username<input name="login" type="text" class="textfield" id="login" />
                 Password<input name="password" type="password" class="textfield" id="password" />
                 Confirm Password<input name="cpassword" type="password" class="textfield" id="cpassword" />
                 <br />
                    Click the Submit button when you are ready
                 </p>
                 <input class='bluesubmit' type="submit" name="Submit" value="Register" />
              </form>

              <h2 id="inline1_sub">Login</h2>
              <form id="myname" name="loginForm" method="post" action="login-exec.php">
                 Username<input name="login" type="text" class="textfield" id="login" />
                 Password<input name="password" type="password" class="textfield" id="password" />
                 <br />
                 <button class='bluesubmit' type="submit" name="Submit" value="Login">Login</button>
                 <p> 
                   You are joining group <span id="group_name"></span>
                 </p>
              </form>
              </div>
  
    
            <div id="disconnected" style="overflow:auto;display: none;" class="alert">
              <h2>Sorry, you've been disconnected - please reload the page.</h2>
            </div>


        <div id="results" style="display:none;">
        <div class="close_button"><img src="images/close.png" width="30px" onclick="javascript:hide_overlay();"></div>
        <div id="group_overlay" class="ui-widget-content large_prog ui-draggable">
           <div style="float:left;"> 
             <img class="img" src="images/group_wide.png" width="150px;"/>
           </div>
           <div style="width:300px;float:left;">
               <div class="p_title_large">Shared</div>
               <p class="description">
                 Shared by friends and shared by you to friends. 
               </p>
               <p>To add people to the group send them this link: <a id="grp_link" href=""></a> by email, twitter or however you like.</p><p>Anyone who clicks on the link can join your group.</p>
           </div>

           <br clear="both"></div>
           <div class='dotted_spacer2'></div>
           <div id="shared"></div>
        </div>



        <div id="history" style="display:none;">
        <div class="close_button"><img src="images/close.png" width="30px" onclick="javascript:hide_overlay();"></div>
        <div id="group_overlay" class="ui-widget-content large_prog ui-draggable">
           <div style="float:left;"> 
             <img class="img" src="images/history_wide.png" width="150px;"/>
           </div>
           <div style="width:300px;float:left;">
               <div class="p_title_large">Recently Viewed</div>
               <p class="description">
                  Recently viewed programmes.
               </p>
           </div>

           <br clear="both"></div>
           <div class='dotted_spacer2'></div>
           <div id="history_items"></div>
        </div>

        <div id="recs" style="display:none;">
        <div class="close_button"><img src="images/close.png" width="30px" onclick="javascript:hide_overlay();"></div>
        <div id="group_overlay" class="ui-widget-content large_prog ui-draggable">
           <div style="float:left;"> 
             <img class="img" src="images/recs_wide.png" width="150px;"/>
           </div>
           <div style="width:300px;float:left;">
               <div class="p_title_large">Recommended</div>
               <p class="description">
                 Recommendations for you.
               </p>
           </div>

           <br clear="both"></div>
           <div class='dotted_spacer2'></div>
           <div id="recs_items"></div>
        </div>

        <div id="ssee" style="display:none;">
        <div class="close_button"><img src="images/close.png" width="30px" onclick="javascript:hide_overlay();"></div>
        <div id="group_overlay" class="ui-widget-content large_prog ui-draggable">
           <div style="float:left;"> 
             <img class="img" src="images/search_results.png" width="150px;"/>
           </div>
           <div style="width:300px;float:left;">
               <div class="p_title_large">Search results</div>
               <p class="description">
                 Last search.
               </p>
           </div>

           <br clear="both"></div>
           <div class='dotted_spacer2'></div>
           <div id="search_items"></div>
        </div>



</div>

</body>

</html>
