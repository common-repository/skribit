=== Plugin Name ===
Contributors: cyu
Tags: widget, suggestions, skribit
Requires at least: 3.0
Tested up to: 3.0
Stable tag: trunk

Help cure writer's block by getting suggestions from your readers with this plugin.

== Description ==

This is the official Skribit plugin for WordPress.  Skribit is a content suggestion service helping bloggers discover relevant topics to write about from their readers.  This plugin makes it easy to add the Skribit sidebar widget and/or Suggestion Tab widget, from which your readers can make or follow suggestions for new posts.

If you want to see an example of what these widgets look like, check out http://Skribit.com/blog and http://PaulStamatiou.com.

== Installation ==

1) Unzip the plugin into your wp-content/plugins/ directory.

2) Login to your WordPress blog and activate the Skribit plugin.

3) Once activated, you should see a prompt to connect your blog to Skribit.com.  If not, you can also start the connection process from **Plugins >> Skribit Configuration**.

4) From the *Skribit Configuration* page, click on **Connect to Skribit**.  You'll be redirected to Skribit.com.

5) You'll be prompted to login if you're not already.  If you don't have account, you'll need to sign up (don't worry, it's easy!).

6) Once logged in, you'll be prompted to authorize your blog access to your Skribit account information.  Check the *authorize access* checkbox and click **Save changes**.  You'll then be redirected to your blog.

7) You're done!  You can now add the sidebar widget to your blog (from **Appearance >> Widgets**) or the Suggestions Tab widget (by checking the *Show LightBox Widget* option).

== Frequently Asked Questions ==

We encourage users to discuss and give feedback about the widget on <a href="http://getsatisfaction.com/skribit/products/skribit_wordpress_plugin">our GetSatisfaction page</a>.  You'll also find some help with customizing the widget there.

== Change Log ==

= 7/6/2010 - v1.0.1 =

* Fixed an issue with setting up the Skribit widget is WP 3.0.

= 4/7/2010 - v1.0 =

* Plugin now links plugin to a Skribit.com account so that entering the Blog Code is no longer needed.

= 3/30/2009 - v0.5.1 =

* Fix a bug where you can't turn off the Lightbox widget (sorry!)

= 3/4/2009 =

* Fixed a bug where the 'Disable CSS' checkbox would on show as checked after a reload of the widget page.
* Added support for showing the Skribit LightBox widget (Settings > Skribit > Show LightBox Widget).  You'll
  need to go through the 'Get Blog Code' again for this option to be available.
* Link to Skribit's suggestion page for your blog in the <noscript> tag.  Only does this if you redo the
  'Get Blog Code' flow.

= 11/15/2008 =

* Fixed an error where the widget would cause pages to occasionally fail in IE
* Added noscript tag for browsers that don't support JavaScript

= 10/26/2008 =

* Removed 'Disable Skribit CSS' field from the admin page (use the option from the widget options page instead)
* Fixed some syntax issues to pass page validations

= 10/22/2008 =

* Load widget script from http://assets.skribit.com
* Fixed bug with 'Get blog code' logic

