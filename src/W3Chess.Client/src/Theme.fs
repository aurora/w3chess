module W3Chess.Client.Theme

type Theme =
    | Light
    | Dark

let toCode = function
    | Light -> "light"
    | Dark -> "dark"

let ofCode (code: string) =
    if code = "light" then Light else Dark
