namespace W3Chess.Server

open Microsoft.Extensions.Configuration

type SmtpSettings =
    { Host: string
      Port: int
      UseStartTls: bool
      Username: string option
      Password: string option
      From: string
      SubjectPrefix: string
      Enabled: bool }

type AppSettings =
    { DatabasePath: string
      LegacyDataDirectory: string
      PublicBaseUrl: string
      TokenSecret: string
      GameRetentionDays: int
      Smtp: SmtpSettings }

module AppSettings =
    let load (config: IConfiguration) : AppSettings =
        let getString key fallback =
            let value = config.[key]
            if System.String.IsNullOrWhiteSpace value then fallback else value

        let getBool key fallback =
            match System.Boolean.TryParse(getString key (string fallback)) with
            | true, value -> value
            | _ -> fallback

        let getInt key fallback =
            match System.Int32.TryParse(getString key (string fallback)) with
            | true, value -> value
            | _ -> fallback

        let optional key =
            let value = getString key ""
            if value = "" then None else Some value

        { DatabasePath = getString "Database:Path" "w3chess.db"
          LegacyDataDirectory = getString "Legacy:DataDirectory" "games"
          PublicBaseUrl = getString "PublicBaseUrl" "http://localhost:5173"
          TokenSecret = getString "Token:Secret" "dev-only-change-me"
          GameRetentionDays = getInt "GameRetentionDays" 0
          Smtp =
            { Host = getString "Smtp:Host" "localhost"
              Port = getInt "Smtp:Port" 1025
              UseStartTls = getBool "Smtp:UseStartTls" false
              Username = optional "Smtp:Username"
              Password = optional "Smtp:Password"
              From = getString "Smtp:From" "w3chess@localhost"
              SubjectPrefix = getString "Smtp:SubjectPrefix" "[W3Chess]"
              Enabled = getBool "Smtp:Enabled" true } }
