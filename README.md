# jQuery Social

A jQuery plugin to manage share buttons.

This plugin allows you to:

- Customize the look of share buttons;
- Track your social share on Google Analytics (instructions soon);

## Supported social platform

- Facebook
- Google+
- Twitter
- Pinterest
- LinkedIn

## Installation

See the example in the demo folder.

## Notes

- Google+ and Twitter does not provide any documented API to fetch the total share count. For this reason,
the counter of these platforms may does not work!
- The tracking for Pinterest, LinkedIn and Google+ is only an "intent": it’s not possible to trace the
real sharing.
- Google Analytics tracks Google+ shares automatically as "social" event. This plugin also adds tracking as
normal event, but the "social" events are more accurate.
