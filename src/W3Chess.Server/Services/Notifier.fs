namespace W3Chess.Server.Services

open W3Chess.Server

/// Seam for game notifications. Only the email implementation exists today; a
/// realtime (SignalR) notifier can be added for online play without changing
/// `GameService`.
type Notifier = { Send: Mail -> Async<unit> }

module Notifier =
    let email (settings: SmtpSettings) =
        { Send = fun mail -> EmailService.send settings mail }
