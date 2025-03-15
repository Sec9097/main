<?php

declare(strict_types=1);

namespace SkyBlock;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\WorldException;
use pocketmine\scheduler\Task;
use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\CustomForm;

class Main extends PluginBase implements Listener {

    /** @var Config */
    private $islandData;

    public function onEnable(): void {
        @mkdir($this->getDataFolder());
        $this->islandData = new Config($this->getDataFolder() . "islands.yml", Config::YAML);
        $this->getLogger()->info("SkyBlock Eklentisi Başlatıldı!");
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }
    
    public function onDisable(): void {
        $this->islandData->save();
    }

    /**
     * Komutlar: /ada (ana menü) ve ekstra komutlar: /tamir, /yemek, /can, /uc, /paralıuc, /uchediye
     */
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if(!$sender instanceof Player){
            $sender->sendPopup("Bu komutu sadece oyuncular kullanabilir!");
            return true;
        }
        $cmd = strtolower($command->getName());
        if($cmd === "ada"){
            // İzin kontrolü (plugin.yml’de skyblock.ada tanımlı)
            if(!$sender->hasPermission("skyblock.ada")){
                $sender->sendPopup(TextFormat::RED . "Bu komutu kullanmak için izniniz yok!");
                return true;
            }
            $this->openAnaMenu($sender);
            return true;
        } elseif(in_array($cmd, ["tamir", "yemek", "can", "uc", "paralıuc", "uchediye"])) {
            switch($cmd){
                case "tamir":
                    $this->handleTamir($sender, $args);
                    break;
                case "yemek":
                    $this->handleYemek($sender, $args);
                    break;
                case "can":
                    $this->handleCan($sender, $args);
                    break;
                case "uc":
                    $this->handleUc($sender, $args);
                    break;
                case "paralıuc":
                    $this->handleParalıUc($sender, $args);
                    break;
                case "uchediye":
                    $this->handleUcHediye($sender, $args);
                    break;
            }
            return true;
        }
        return false;
    }

    /**********************
     * ADA YÖNETİM MENÜ FONKSİYONLARI (Orijinal kodun tüm fonksiyonları)
     **********************/
    
    public function openAnaMenu(Player $player): void {
        $hasIsland = $this->hasIsland($player);
        $form = new SimpleForm(function(Player $player, $data) use ($hasIsland){
            if($data === null) return;
            if($hasIsland){
                switch($data){
                    case 0:
                        $this->openYonetimMenu($player);
                        break;
                    case 1:
                        $this->openOrtakIslemleriMenu($player);
                        break;
                    case 2:
                        $this->openIslandInfoMenu($player);
                        break;
                    case 3:
                        $this->openAyarlarMenu($player);
                        break;
                    case 4:
                        $this->openGorevMenu($player);
                        break;
                    case 5:
                        $this->openIhaleMenu($player);
                        break;
                }
            } else {
                switch($data){
                    case 0:
                        $this->openAdaOlusturMenu($player);
                        break;
                    case 1:
                        $this->openGorevMenu($player);
                        break;
                    case 2:
                        $this->openIhaleMenu($player);
                        break;
                }
            }
        });
        if($hasIsland){
            $form->setTitle("Ada Yönetimi");
            $form->setContent("Adanıza ilişkin işlemleri seçin:");
            $form->addButton("Ada Yönetimi", 0, "assets/minecraft/textures/ui/island_management.png");
            $form->addButton("Ortak İşlemleri", 0, "assets/minecraft/textures/ui/member_operations.png");
            $form->addButton("Ada Bilgisi", 0, "assets/minecraft/textures/ui/island_info.png");
            $form->addButton("Ada Ayarları", 0, "assets/minecraft/textures/ui/island_settings.png");
            $form->addButton("Görev Sistemi", 0, "assets/minecraft/textures/ui/quest.png");
            $form->addButton("İhale / Takas", 0, "assets/minecraft/textures/ui/auction.png");
        } else {
            $form->setTitle("SkyBlock Ana Menü");
            $form->setContent("Henüz adanız yok. Lütfen aşağıdan ada oluşturun veya diğer işlemleri yapın:");
            $form->addButton("Ada Oluştur", 0, "assets/minecraft/textures/ui/create_island.png");
            $form->addButton("Görev Sistemi", 0, "assets/minecraft/textures/ui/quest.png");
            $form->addButton("İhale / Takas", 0, "assets/minecraft/textures/ui/auction.png");
        }
        $player->sendForm($form);
    }

    public function openYonetimMenu(Player $player): void {
        $form = new SimpleForm(function(Player $player, $data){
            if($data === null) return;
            switch($data){
                case 0:
                    $this->openTeleportSelectionMenu($player);
                    break;
                case 1:
                    $this->openIslandInfoMenu($player);
                    break;
                case 2:
                    $this->openAyarlarMenu($player);
                    break;
                case 3:
                    $this->openOrtakIslemleriMenu($player);
                    break;
                case 4:
                    $this->openDeleteIslandConfirmation($player);
                    break;
            }
        });
        $form->setTitle("Ada Yönetimi");
        $form->setContent("Lütfen bir işlem seçin:");
        $form->addButton("Ada Işınlan", 0, "assets/minecraft/textures/ui/teleport.png");
        $form->addButton("Ada Bilgisi", 0, "assets/minecraft/textures/ui/island_info.png");
        $form->addButton("Ada Ayarları", 0, "assets/minecraft/textures/ui/island_settings.png");
        $form->addButton("Ortak İşlemleri", 0, "assets/minecraft/textures/ui/member_operations.png");
        $form->addButton("Ada Silme", 0, "assets/minecraft/textures/ui/delete.png");
        $player->sendForm($form);
    }

    public function openAdaOlusturMenu(Player $player): void {
        if($this->hasIsland($player)){
            $player->sendPopup("Zaten bir adanız var!");
            $this->openYonetimMenu($player);
            return;
        }
        $form = new SimpleForm(function(Player $player, $data){
            if($data === null) return;
            if($player->hasPermission("skyblock.vip")){
                switch($data){
                    case 0:
                        $this->createIsland($player, true);
                        break;
                    case 1:
                        $this->createIsland($player, false);
                        break;
                }
            } else {
                if($data === 0){
                    $this->createIsland($player, false);
                }
            }
        });
        $form->setTitle("Ada Oluştur");
        $form->setContent("Oluşturmak istediğiniz ada tipini seçin:");
        if($player->hasPermission("skyblock.vip")){
            $form->addButton("VIP Ada", 0, "assets/minecraft/textures/ui/vip_island.png");
            $form->addButton("Normal Ada", 0, "assets/minecraft/textures/ui/normal_island.png");
        } else {
            $form->addButton("Normal Ada", 0, "assets/minecraft/textures/ui/normal_island.png");
        }
        $player->sendForm($form);
    }

    public function createIsland(Player $player, bool $vip): void {
        $templateWorldName = $vip ? "VIPAda" : "NormalAda";
        $newWorldName = "ada_" . strtolower($player->getName());
        $server = Server::getInstance();

        if(!$this->cloneWorld($templateWorldName, $newWorldName)){
            $player->sendPopup("Ada dünyası kopyalanamadı!");
            return;
        }
        if(!$server->getWorldManager()->loadWorld($newWorldName)){
            $player->sendPopup("Yeni ada dünyası yüklenemedi!");
            return;
        }
        $world = $server->getWorldManager()->getWorldByName($newWorldName);
        if($world === null){
            $player->sendPopup("Ada dünyası bulunamadı!");
            return;
        }
        try {
            $pos = $world->getSafeSpawn();
        } catch (WorldException $e) {
            $pos = new Vector3(0, 64, 0);
            $world->setSpawnLocation($pos);
            $world->loadChunk((int)$pos->getX(), (int)$pos->getZ(), true);
        }
        $data = [
            "owner" => $player->getName(),
            "vip" => $vip,
            "members" => [],
            "member_permissions" => [],
            "level" => 1,
            "blockCount" => 0,
            "blockLimit" => 500,
            "home" => [
                "x" => $pos->getX(),
                "y" => $pos->getY(),
                "z" => $pos->getZ()
            ],
            "world" => $newWorldName,
            "time" => "day",
            "timeCycle" => true,
            "permissions" => [
                "allowPlace" => true,
                "allowBreak" => true,
                "allowChest" => true
            ],
            "visit" => true
        ];
        $this->islandData->set(strtolower($player->getName()), $data);
        $this->islandData->save();
        $player->sendPopup(($vip ? "VIP" : "Normal") . " ada başarıyla oluşturuldu!");
        $this->openYonetimMenu($player);
    }

    private function recursiveCopy(string $src, string $dst): bool {
        if(!is_dir($src)){
            return false;
        }
        if(!is_dir($dst)){
            mkdir($dst, 0777, true);
        }
        $dir = opendir($src);
        if($dir === false) return false;
        while(($file = readdir($dir)) !== false){
            if($file === '.' || $file === '..'){
                continue;
            }
            $srcPath = $src . DIRECTORY_SEPARATOR . $file;
            $dstPath = $dst . DIRECTORY_SEPARATOR . $file;
            if(is_dir($srcPath)){
                if(!$this->recursiveCopy($srcPath, $dstPath)){
                    return false;
                }
            } else {
                if(!copy($srcPath, $dstPath)){
                    return false;
                }
            }
        }
        closedir($dir);
        return true;
    }
    
    public function cloneWorld(string $templateWorldName, string $newWorldName): bool {
        $serverDataPath = Server::getInstance()->getDataPath();
        $worldsPath = $serverDataPath . "worlds" . DIRECTORY_SEPARATOR;
        $srcPath = $worldsPath . $templateWorldName;
        $dstPath = $worldsPath . $newWorldName;
        if(!is_dir($srcPath)){
            $this->getLogger()->error("Template dünya '$templateWorldName' bulunamadı!");
            return false;
        }
        if(is_dir($dstPath)){
            $this->deleteDirectory($dstPath);
        }
        return $this->recursiveCopy($srcPath, $dstPath);
    }
    
    private function deleteDirectory(string $dir): bool {
        if(!file_exists($dir)){
            return true;
        }
        if(!is_dir($dir)){
            return unlink($dir);
        }
        foreach(scandir($dir) as $item){
            if($item === '.' || $item === '..'){
                continue;
            }
            if(!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)){
                return false;
            }
        }
        return rmdir($dir);
    }
    
    public function hasIsland(Player $player): bool {
        return $this->islandData->exists(strtolower($player->getName()));
    }

    public function openIslandInfoMenu(Player $player): void {
        if(!$this->hasIsland($player)){
            $player->sendPopup("Adanız bulunmuyor!");
            return;
        }
        $data = $this->islandData->get(strtolower($player->getName()));
        $basicInfo = "Sahibi: " . $data["owner"] . "\n";
        $basicInfo .= "Ada Seviyesi: " . $data["level"] . "\n";
        $basicInfo .= "Konulan Blok: " . $data["blockCount"] . "/" . $data["blockLimit"] . "\n";
        $basicInfo .= "Ada Zamanı: " . $data["time"] . "\n";
        $members = (isset($data["members"]) && count($data["members"]) > 0) ? implode(", ", $data["members"]) : "Yok";
        $form = new CustomForm(function(Player $player, $data) {
            // Görüntüleme amaçlı geri dönüş işlemi gerekmez.
        });
        $form->setTitle("Ada Bilgisi");
        $form->addLabel("Temel Bilgiler:\n" . $basicInfo);
        $form->addLabel("Ortaklar:\n" . $members);
        $player->sendForm($form);
    }

    /**
     * **Ortak İzinleri Menüsü**  
     * Ada ortakları listelenir; seçilen her ortak için ayrı izin menüsü açılır.
     */
    public function openOrtakIslemleriMenu(Player $player): void {
        if(!$this->hasIsland($player)){
            $player->sendPopup("Adanız bulunmuyor!");
            return;
        }
        $island = $this->islandData->get(strtolower($player->getName()));
        $members = $island["members"];
        if(empty($members)){
            $player->sendPopup("Henüz eklenmiş ortak bulunmuyor.");
            return;
        }
        $form = new SimpleForm(function(Player $player, $data) use ($members) {
            if($data === null) return;
            // Seçilen buton indeksiyle ortak belirlenir.
            $memberName = $members[$data];
            $this->openMemberPermissionMenu($player, $memberName);
        });
        $form->setTitle("Ortaklar ve İzinler");
        $form->setContent("İzinlerini düzenlemek istediğiniz kişiyi seçin:");
        foreach($members as $member) {
            $form->addButton($member);
        }
        $player->sendForm($form);
    }

    /**
     * **Ortak İzinleri Düzenleme Menüsü**  
     * Seçilen ortak için; blok koyma, blok kırma, sandık açma, blok atma ve item alma izinleri ayarlanır.
     */
    public function openMemberPermissionMenu(Player $player, string $memberName): void {
        $ownerName = strtolower($player->getName());
        $island = $this->islandData->get($ownerName);
        if(!isset($island["member_permissions"][$memberName])){
            $island["member_permissions"][$memberName] = [
                "allowPlace" => true,
                "allowBreak" => true,
                "allowChest" => true,
                "allowBlockThrow" => true,
                "allowItemPickup" => true
            ];
            $this->islandData->set($ownerName, $island);
            $this->islandData->save();
        }
        $permissions = $island["member_permissions"][$memberName];
        $form = new CustomForm(function(Player $player, $data) use ($memberName, $ownerName) {
            if($data === null) return;
            $island = $this->islandData->get($ownerName);
            $island["member_permissions"][$memberName] = [
                "allowPlace" => $data[1],
                "allowBreak" => $data[2],
                "allowChest" => $data[3],
                "allowBlockThrow" => $data[4],
                "allowItemPickup" => $data[5]
            ];
            $this->islandData->set($ownerName, $island);
            $this->islandData->save();
            $player->sendPopup("$memberName için izinler güncellendi!");
        });
        $form->setTitle("$memberName - İzin Ayarları");
        $form->addLabel("Lütfen izinleri ayarlayın:");
        $form->addToggle("Blok Koyma", $permissions["allowPlace"]);
        $form->addToggle("Blok Kırma", $permissions["allowBreak"]);
        $form->addToggle("Sandık Açma", $permissions["allowChest"]);
        $form->addToggle("Blok Atma", $permissions["allowBlockThrow"]);
        $form->addToggle("Item Alma", $permissions["allowItemPickup"]);
        $player->sendForm($form);
    }

    public function openAyarlarMenu(Player $player): void {
        if(!$this->hasIsland($player)){
            $player->sendPopup("Adanız bulunmuyor!");
            return;
        }
        $form = new SimpleForm(function(Player $player, $data){
            if($data === null) return;
            switch($data){
                case 0:
                    $this->openTimeSettingForm($player);
                    break;
                case 1:
                    $this->openSetHomeForm($player);
                    break;
                case 2:
                    $this->openTransferIslandForm($player);
                    break;
                case 3:
                    $this->openKickPlayerForm($player);
                    break;
                case 4:
                    $this->openDeleteIslandConfirmation($player);
                    break;
            }
        });
        $form->setTitle("Ada Ayarları");
        $form->setContent("Lütfen bir ayar seçin:");
        $form->addButton("Ada Zamanını Ayarla", 0, "assets/minecraft/textures/ui/island_time.png");
        $form->addButton("Başlangıç Noktasını Ayarla", 0, "assets/minecraft/textures/ui/set_home.png");
        $form->addButton("Ada Devretme", 0, "assets/minecraft/textures/ui/transfer.png");
        $form->addButton("Oyuncu Tekmeleme", 0, "assets/minecraft/textures/ui/kick.png");
        $form->addButton("Ada Silme", 0, "assets/minecraft/textures/ui/delete.png");
        $player->sendForm($form);
    }

    public function openTimeSettingForm(Player $player): void {
        $form = new CustomForm(function(Player $player, $data){
            if($data === null) return;
            $timeOption = $data[1];
            $cycleActive = $data[2];
            $island = $this->islandData->get(strtolower($player->getName()));
            $island["time"] = ($timeOption === 0) ? "day" : "night";
            $island["timeCycle"] = $cycleActive;
            $this->islandData->set(strtolower($player->getName()), $island);
            $this->islandData->save();
            $server = Server::getInstance();
            $world = $server->getWorldManager()->getWorldByName($island["world"]);
            if($world !== null){
                $world->setTime(($timeOption === 0) ? 6000 : 18000);
                if(!$cycleActive){
                    $player->sendPopup("Ada zamanı döngüsü durduruldu.");
                } else {
                    $player->sendPopup("Ada zamanı döngüsü aktif.");
                }
            }
        });
        $form->setTitle("Ada Zamanını Ayarla");
        $form->addLabel("Aşağıdaki seçenek ile ada zamanını ve döngüsünü ayarlayabilirsiniz.");
        $form->addDropdown("Zaman Seçimi:", ["Gündüz", "Gece"]);
        $form->addToggle("Zaman Döngüsü Aktif Mi?", true);
        $player->sendForm($form);
    }
    
    public function openSetHomeForm(Player $player): void {
        $island = $this->islandData->get(strtolower($player->getName()));
        $server = Server::getInstance();
        $islandWorld = $server->getWorldManager()->getWorldByName($island["world"]);
        if($islandWorld === null || $player->getWorld()->getDisplayName() !== $islandWorld->getDisplayName()){
            $player->sendPopup("Başlangıç noktasını sadece kendi adanızda ayarlayabilirsiniz!");
            return;
        }
        $pos = $player->getPosition();
        $island["home"] = [
            "x" => $pos->getX(),
            "y" => $pos->getY(),
            "z" => $pos->getZ()
        ];
        $this->islandData->set(strtolower($player->getName()), $island);
        $this->islandData->save();
        $player->sendPopup("Başlangıç noktası başarıyla ayarlandı.");
    }
    
    public function openDeleteIslandConfirmation(Player $player): void {
        $form = new SimpleForm(function(Player $player, $data){
            if($data === null) return;
            if($data === 0){
                $this->deleteIsland($player);
            } else {
                $player->sendPopup("Ada silme işlemi iptal edildi.");
            }
        });
        $form->setTitle("Ada Silme");
        $form->setContent("Adanızı silmek istediğinize emin misiniz? Bu işlem geri alınamaz.");
        $form->addButton("Evet, sil", 0, "assets/minecraft/textures/ui/delete.png");
        $form->addButton("İptal", 0, "assets/minecraft/textures/ui/cancel.png");
        $player->sendForm($form);
    }
    
    public function deleteIsland(Player $player): void {
        $data = $this->islandData->get(strtolower($player->getName()));
        $worldName = $data["world"];
        $this->islandData->remove(strtolower($player->getName()));
        $this->islandData->save();
        $server = Server::getInstance();
        $world = $server->getWorldManager()->getWorldByName($worldName);
        if($world !== null){
            $server->getWorldManager()->unloadWorld($world, true);
        }
        $serverDataPath = $server->getDataPath();
        $worldsPath = $serverDataPath . "worlds" . DIRECTORY_SEPARATOR;
        $this->deleteDirectory($worldsPath . $worldName);
        $player->sendPopup("Ada başarıyla silindi!");
    }
    
    public function openGorevMenu(Player $player): void {
        $form = new SimpleForm(function(Player $player, $data){
            if($data === null) return;
            $player->sendPopup("Görev sistemi aktif! Henüz görev bulunmuyor.");
        });
        $form->setTitle("Görev Sistemi");
        $form->setContent("Görevlerinizi tamamlayarak ödül kazanın!");
        $form->addButton("Görevleri Görüntüle", 0, "assets/minecraft/textures/ui/quest.png");
        $player->sendForm($form);
    }
    
    public function openIhaleMenu(Player $player): void {
        $form = new SimpleForm(function(Player $player, $data){
            if($data === null) return;
            $player->sendPopup("İhale / Takas sistemi aktif! Henüz ihale bulunmuyor.");
        });
        $form->setTitle("İhale / Takas");
        $form->setContent("Eşya ticareti için seçenekleri inceleyin.");
        $form->addButton("İhale / Takas", 0, "assets/minecraft/textures/ui/auction.png");
        $player->sendForm($form);
    }
    
    public function openKickPlayerForm(Player $player): void {
        if(!$this->hasIsland($player)){
            $player->sendPopup("Önce adanızı oluşturun!");
            return;
        }
        $island = $this->islandData->get(strtolower($player->getName()));
        $world = Server::getInstance()->getWorldManager()->getWorldByName($island["world"]);
        if($world === null){
            $player->sendPopup("Ada dünyası bulunamadı!");
            return;
        }
        $onlinePlayers = [];
        foreach($world->getPlayers() as $p) {
            if($p->getName() === $player->getName()) continue;
            $onlinePlayers[] = $p->getName();
        }
        if(count($onlinePlayers) === 0){
            $player->sendPopup("Tekmelemek için ada içerisinde başka oyuncu bulunamadı.");
            return;
        }
        $form = new CustomForm(function(Player $player, $data) use ($onlinePlayers) {
            if($data === null) return;
            $selectedIndex = $data[1];
            $kickName = $onlinePlayers[$selectedIndex];
            $island = $this->islandData->get(strtolower($player->getName()));
            if(($key = array_search($kickName, $island["members"])) !== false){
                unset($island["members"][$key]);
                $island["members"] = array_values($island["members"]);
                if(isset($island["member_permissions"][$kickName])){
                    unset($island["member_permissions"][$kickName]);
                }
                $this->islandData->set(strtolower($player->getName()), $island);
                $this->islandData->save();
            }
            $mainWorld = Server::getInstance()->getWorldManager()->getDefaultWorld();
            if($mainWorld !== null){
                $p = Server::getInstance()->getPlayerExact($kickName);
                if($p !== null){
                    $p->teleport($mainWorld->getSafeSpawn());
                    $p->sendPopup("Adanızdan atıldınız!");
                }
            }
            $player->sendPopup("$kickName adadan atıldı!");
        });
        $form->setTitle("Oyuncu Tekmeleme");
        $form->addLabel("Aşağıdan tekmelemek istediğiniz oyuncuyu seçin:");
        $form->addDropdown("Oyuncular:", $onlinePlayers);
        $player->sendForm($form);
    }
    
    public function openTransferIslandForm(Player $player): void {
        if(!$this->hasIsland($player)){
            $player->sendPopup("Önce adanızı oluşturun!");
            return;
        }
        $online = [];
        foreach(Server::getInstance()->getOnlinePlayers() as $p) {
            if($p->getName() === $player->getName()) continue;
            $online[] = $p->getName();
        }
        if(count($online) === 0){
            $player->sendPopup("Devretmek için online oyuncu bulunamadı.");
            return;
        }
        $form = new CustomForm(function(Player $player, $data) use ($online) {
            if($data === null) return;
            $selectedIndex = $data[1];
            $targetName = $online[$selectedIndex];
            $this->sendTransferRequest($player, $targetName);
        });
        $form->setTitle("Ada Devretme");
        $form->addLabel("Aşağıdan devretmek istediğiniz oyuncuyu seçin:");
        $form->addDropdown("Oyuncular:", $online);
        $player->sendForm($form);
    }
    
    public function sendTransferRequest(Player $owner, string $targetName): void {
        $target = Server::getInstance()->getPlayerExact($targetName);
        if($target === null){
            $owner->sendPopup("$targetName şu anda online değil.");
            return;
        }
        $form = new CustomForm(function(Player $target, $data) use ($owner) {
            if($data === null) return;
            if($data[1]){
                $island = $this->islandData->get(strtolower($owner->getName()));
                $island["owner"] = $target->getName();
                $this->islandData->remove(strtolower($owner->getName()));
                $this->islandData->set(strtolower($target->getName()), $island);
                $this->islandData->save();
                $target->sendPopup("{$owner->getName()} adanızın devrini kabul etti.");
                $owner->sendPopup("Ada devri gerçekleşti. Yeni sahip: " . $target->getName());
            } else {
                $owner->sendPopup($target->getName() . " devretme davetinizi reddetti.");
            }
        });
        $form->setTitle("Devretme Daveti");
        $form->addLabel("{$owner->getName()} adanızı devretmek istiyor. Kabul ediyor musunuz?");
        $form->addToggle("Kabul Ediyorum", false);
        $target->sendForm($form);
        $owner->sendPopup("$targetName'e devretme davetiniz gönderildi.");
    }
    
    public function openTeleportSelectionMenu(Player $player): void {
        if(!$this->hasIsland($player)){
            $player->sendPopup("Adanız bulunmuyor!");
            return;
        }
        $data = $this->islandData->get(strtolower($player->getName()));
        $server = Server::getInstance();
        $world = $server->getWorldManager()->getWorldByName($data["world"]);
        if($world === null){
            if(!$server->getWorldManager()->loadWorld($data["world"])){
                $player->sendPopup("Ada dünyası yüklenemedi!");
                return;
            }
            $world = $server->getWorldManager()->getWorldByName($data["world"]);
            if($world === null){
                $player->sendPopup("Ada dünyası bulunamadı!");
                return;
            }
        }
        $home = $data["home"];
        $position = new Position($home["x"], $home["y"], $home["z"], $world);
        $player->teleport($position);
        $player->sendPopup("Adanıza ışınlandınız!");
    }
    
    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $name = strtolower($player->getName());
        if($this->islandData->exists($name)){
            $data = $this->islandData->get($name);
            $worldName = $data["world"];
            if(in_array($worldName, ["NormalAda", "VIPAda"])) {
                return;
            }
            $server = Server::getInstance();
            if($server->getWorldManager()->isWorldLoaded($worldName)){
                $world = $server->getWorldManager()->getWorldByName($worldName);
                if($world !== null){
                    try {
                        $server->getWorldManager()->unloadWorld($world, true);
                        $this->getLogger()->info("Oyuncu {$player->getName()} çıkarken adası unload edildi: $worldName");
                    } catch (\Exception $e) {
                        $this->getLogger()->error("Ada unload edilirken hata oluştu: " . $e->getMessage());
                    }
                }
            }
        }
    }

    /**********************
     * EKSTRA KOMUT İŞLEYİCİLERİ
     * (/tamir, /yemek, /can, /uc, /paralıuc, /uchediye)
     **********************/
    
    /**
     * /tamir - Elindeki eşyayı onarır.
     */
    private function handleTamir(Player $player, array $args): void {
         $item = $player->getInventory()->getItemInHand();
         if($item->isNull()){
              $player->sendMessage(TextFormat::RED . "Elinizde onarılacak bir eşya yok!");
              return;
         }
         $item->setDamage(0);
         $player->getInventory()->setItemInHand($item);
         $player->sendMessage(TextFormat::GREEN . "Eşyanız onarıldı!");
    }
    
    /**
     * /yemek - Açlık barını tamamen doldurur.
     */
    private function handleYemek(Player $player, array $args): void {
         $player->setFood(20);
         $player->sendMessage(TextFormat::GREEN . "Yemek yediniz, açlık doldu!");
    }
    
    /**
     * /can - Canını tamamen yeniler.
     */
    private function handleCan(Player $player, array $args): void {
         $player->setHealth($player->getMaxHealth());
         $player->sendMessage(TextFormat::GREEN . "Canınız yenilendi!");
    }
    
    /**
     * /uc - Uçma modunu açar/kapatır (izin kontrolü).
     */
    private function handleUc(Player $player, array $args): void {
         if(!$player->hasPermission("skyblock.fly")){
              $player->sendMessage(TextFormat::RED . "Bu komutu kullanmak için izniniz yok!");
              return;
         }
         $player->setAllowFlight(!$player->getAllowFlight());
         $status = $player->getAllowFlight() ? "aktif" : "deaktif";
         $player->sendMessage(TextFormat::GREEN . "Uçma modu $status hale getirildi!");
    }
    
    /**
     * /paralıuc - 500 saniyeliğine uçma modunu ücret karşılığı aktif eder.
     */
    private function handleParalıUc(Player $player, array $args): void {
         if(!$player->hasPermission("skyblock.fly.pay")){
              $player->sendMessage(TextFormat::RED . "Bu komutu kullanmak için izniniz yok!");
              return;
         }
         $player->setAllowFlight(true);
         $player->sendMessage(TextFormat::GREEN . "500 saniyeliğine uçma modu aktif edildi!");
         $this->getScheduler()->scheduleDelayedTask(new class($player) extends Task {
             private $player;
             public function __construct(Player $player) {
                 $this->player = $player;
             }
             public function onRun(): void {
                 if(!$this->player->isOnline()) return;
                 $this->player->setAllowFlight(false);
                 $this->player->sendMessage(TextFormat::RED . "Uçma süreniz doldu!");
             }
         }, 500 * 20);
    }
    
    /**
     * /uchediye - Belirtilen oyuncuya 500 saniyeliğine uçma süresi hediye eder.
     * Kullanım: /uchediye <oyuncu>
     */
    private function handleUcHediye(Player $player, array $args): void {
         if(count($args) < 1){
              $player->sendMessage(TextFormat::RED . "Kullanım: /uchediye <oyuncu>");
              return;
         }
         $targetName = array_shift($args);
         $target = $this->getServer()->getPlayerExact($targetName);
         if($target === null){
              $player->sendMessage(TextFormat::RED . "Belirtilen oyuncu bulunamadı!");
              return;
         }
         $target->setAllowFlight(true);
         $target->sendMessage(TextFormat::GREEN . "Bir oyuncu size 500 saniyeliğine uçma süresi hediye etti!");
         $player->sendMessage(TextFormat::GREEN . "$targetName oyuncusuna uçma süresi hediye edildi!");
         $this->getScheduler()->scheduleDelayedTask(new class($target) extends Task {
             private $target;
             public function __construct(Player $target) {
                 $this->target = $target;
             }
             public function onRun(): void {
                 if(!$this->target->isOnline()) return;
                 $this->target->setAllowFlight(false);
                 $this->target->sendMessage(TextFormat::RED . "Uçma hediye süreniz doldu!");
             }
         }, 500 * 20);
    }
}
