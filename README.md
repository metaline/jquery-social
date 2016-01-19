# jQuery Social

A jQuery plugin to manage share buttons.

This plugin allows you to:

- Customize the look of share buttons;
- Track your social share on Google Analytics (instructions soon);

## Supported social sites

- Facebook
- Google+
- Twitter
- Pinterest
- LinkedIn

## How to install

See the example in the demo folder.

## Notes

- Google+ and Twitter don’t provide any API to fetch the total share count. For this reason,
the count of these platforms may not work!
- The tracking for Pinterest, LinkedIn and Google+ is only an "intent": it’s not possible to trace the
real sharing.
- Google Analytics tracks Google+ shares automatically as "social" event. This plugin also adds tracking as
normal event, but the "social" stats are more accurate.

## Contributing

- Install [Sass](http://sass-lang.com/install)
- Fork and clone `git clone https://github.com/USERNAME/jquery-social`
- Enter project `cd jquery-social`
- Run `scss --style=compressed --watch demo/css/style.scss:demo/css/style.css`
