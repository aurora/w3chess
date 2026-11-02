namespace W3Chess.Server.Services

open W3Chess.Server
open MimeKit
open MailKit.Net.Smtp
open MailKit.Security

/// Sends plain-text mail through MailKit. When SMTP is disabled the message is
/// written to the console instead, which keeps development and tests offline.
module EmailService =
    let send (settings: SmtpSettings) (mail: Mail) : Async<unit> =
        async {
            if not settings.Enabled then
                printfn "[email] to=%s subject=%s\n%s" mail.To mail.Subject mail.Body
            else
                let message = new MimeMessage()
                message.From.Add(MailboxAddress.Parse settings.From) |> ignore
                message.To.Add(MailboxAddress.Parse mail.To) |> ignore
                message.Subject <- settings.SubjectPrefix + " " + mail.Subject
                message.Body <- TextPart("plain", mail.Body)

                use client = new SmtpClient()
                let options =
                    if settings.UseStartTls then SecureSocketOptions.StartTls
                    else SecureSocketOptions.None

                do! client.ConnectAsync(settings.Host, settings.Port, options) |> Async.AwaitTask

                match settings.Username, settings.Password with
                | Some user, Some password ->
                    do! client.AuthenticateAsync(user, password) |> Async.AwaitTask
                | _ -> ()

                let! _ = client.SendAsync(message) |> Async.AwaitTask
                do! client.DisconnectAsync(true) |> Async.AwaitTask
        }
