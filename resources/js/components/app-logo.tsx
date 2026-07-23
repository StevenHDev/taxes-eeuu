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
                className="w-full object-contain group-data-[collapsible=icon]:hidden"
            />
        </>
    );
}
