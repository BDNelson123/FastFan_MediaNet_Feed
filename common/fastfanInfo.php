<?php

if (
    ! isset($_SESSION['fastfanInfo'])  ||
    ! isset($_SESSION['fastfanInfo']['timeout'])  ||
    ( isset($_SESSION['fastfanInfo']['timeout']) && $_SESSION['fastfanInfo']['timeout'] < time() )   ) {

    $query = "SELECT * FROM fastfanInfo";
    $db->query($query);
    $fastfanInfo = $db->fetch_assoc();

    $_SESSION['fastfanInfo'] = $fastfanInfo;
    $_SESSION['fastfanInfo']['timeout'] = time() + (int)60;       // fastfanInfo table changes take effect every minute
}



function fastfanPricing($price)
{
    if ($price <= 0) {
        return $_SESSION['fastfanInfo']['defaultFastfanPrice'];
    }
    return $price;
}

function fastfanMaxSlots($slots)
{
    if ($slots <= 0) {
        return $_SESSION['fastfanInfo']['defaultFastfanMaxSlots'];
    }
    return $slots;
}

function silverPricing($price)
{
    if ($price <= 0) {
        return $_SESSION['fastfanInfo']['defaultSilverPrice'];
    }
    return $price;
}

function silverMaxSlots($slots)
{
    if ($slots <= 0) {
        return $_SESSION['fastfanInfo']['defaultSilverMaxSlots'];
    }
    return $slots;
}


?>