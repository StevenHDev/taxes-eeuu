export default function AppLogo() {
    return (
        <>
            <img
                src="/images/logo-mark.png"
                alt="GTS"
                className="hidden size-8 object-contain group-data-[collapsible=icon]:block"
            />
            <img
                src="/images/logo.png"
                alt="Global Tax Services"
                className="h-8 w-auto object-contain group-data-[collapsible=icon]:hidden"
            />
        </>
    );
}
