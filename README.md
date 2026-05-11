# The Village - Trust-Based P2P Social Network

A decentralized social network built on trust, reputation, and community-driven resource sharing. Built with Laravel 13 (backend) and Dart 3.11/Wasm (frontend).

## 🏗️ Architecture

### Backend (village_engine)
- **Framework**: Laravel 13 with PHP 8.3
- **Database**: MySQL 8.0 with reputation-centric schema
- **Real-time**: Laravel Reverb for P2P signaling
- **Authentication**: FIDO2/WebAuthn Passkey support
- **Caching**: Database driver for sessions (LAMP stack compatible)

### Frontend (village_ui)
- **Framework**: Dart 3.11 compiled to WebAssembly
- **3D Rendering**: Native Dart engine with Skwasm renderer
- **Performance**: WasmGC optimized for maximum security
- **P2P**: WebRTC/WebTransport with libp2p interop

### Infrastructure
- **3-Node Cluster**: village_alpha, village_beta, village_gamma
- **Load Balancing**: Nginx with failover support
- **Containerization**: Docker Compose orchestration
- **Development**: Hot reload with live updates

## 🎯 Core Features

### Reputation System
- **Staked Integrity**: Users stake reputation to vouch for others
- **Contextual Scoring**: Different reputation per village/map/global
- **Time-Based Erosion**: Exponential decay with inertia coefficient
- **Vouching Mechanics**: 20% bonus for successful vouches
- **Role Progression**: Stranger → Sojourner → Denizen → Steward → Elder

### P2P Network
- **Decentralized**: No centralized relays, direct peer connections
- **Signaling**: Reverb server for WebRTC handshakes
- **Discovery**: Kademlia DHT for peer finding
- **Security**: End-to-end encryption with cryptographic proofs

### 3D Spatial Interface
- **Force-Directed Graph**: Villages positioned using Hooke's/Coulomb's laws
- **3-Degree Separation**: Users see only trusted network within 3 hops
- **LOD System**: Level-of-detail for performance optimization
- **Real-time Updates**: Spatial partitioning with efficient culling

### Encrypted Domicile
- **Zero-Knowledge Privacy**: Complete data encryption
- **Aggregated Feeds**: Social media integration with consent
- **Handshake Gateway**: Multi-village access control
- **Workstation Tools**: Brand tracking for creative projects
- **Trust-Action System**: Explicit consent for data sharing

## 🚀 Quick Start

### Prerequisites
- Docker & Docker Compose
- PHP 8.3+ (for local development)
- Dart 3.11+ SDK
- Node.js 18+ (for Dart toolchain)

### Installation

1. **Clone Repository**
```bash
git clone <repository-url>
cd TheVillage
```

2. **Start Infrastructure**
```bash
docker-compose up -d
```

3. **Access Services**
- **village_alpha**: http://localhost:8001
- **village_beta**: http://localhost:8002  
- **village_gamma**: http://localhost:8003
- **Reverb**: ws://localhost:8080
- **MySQL**: localhost:3306

4. **Frontend Development**
```bash
cd village_ui
dart pub get
dart run build:wasm
```

## 📊 Development Roadmap

### Week 1: Infrastructure & Integrity Engine
- [x] Docker environment setup
- [x] Database schema with migrations
- [x] ReputationService implementation
- [x] Core trust logic testing

### Week 2: P2P Signaling & The Gateway  
- [x] Reverb WebSocket configuration
- [x] Stranger-to-Sojourner handshake
- [x] Passkey authentication
- [x] Village recommendation system

### Week 3: The Spatial UI & The Map
- [x] Dart Wasm 3D engine
- [x] Force-directed village visualization
- [x] 3-degree separation filtering
- [x] Interactive navigation

### Week 4: The Domicile & Pilot Launch
- [x] Encrypted personal spaces
- [x] Brand tracking tools
- [x] 5-user stress test
- [x] Production deployment

## 🔧 Configuration

### Environment Variables
Key configuration options in `.env`:

```env
# Reputation System
INTEGRITY_DECAY_RATE=0.02
VOUCH_BONUS_RATE=0.20
MAXIMUM_STAKE_PERCENTAGE=0.50

# P2P Network
P2P_NODE_ID=alpha_node
P2P_BOOTSTRAP_NODES=beta_node:9001,gamma_node:9001
P2P_LISTEN_PORT=9001

# 3D Rendering
WEBGL_RENDERER=webgl2
MAX_ENTITIES_PER_SCENE=1000
SPATIAL_PARTITIONING_ENABLED=true
```

### Database Schema
The reputation system uses these key tables:

- **users**: Base integrity, available/locked staking, Passkey auth
- **villages**: 3D positioning, relationships, population limits
- **reputation_transactions**: Complete audit trail with 13 transaction types
- **contextual_reputations**: Village/map/global reputation with weighted factors
- **vouches**: Staking mechanics with 20% bonus, expiration, resolution
- **village_relationships**: Force-directed graph parameters, trust metrics
- **user_villages**: Membership management, permissions, activity tracking

## 🧪 Testing

### Backend Tests
```bash
cd village_engine
php artisan test
```

### Frontend Tests
```bash
cd village_ui
dart test
```

### Integration Tests
```bash
# Test P2P connectivity between nodes
docker-compose exec village_alpha php artisan test:p2p

# Test reputation calculations
docker-compose exec village_alpha php artisan test:reputation

# Test 3D rendering performance
dart test:performance
```

## 📈 Performance Metrics

### Targets
- **Response Time**: <100ms for 95% of requests
- **Uptime**: 99.9% availability across all nodes
- **3D Rendering**: 60fps with 50+ villages
- **P2P Latency**: <500ms for peer connections
- **Memory Usage**: <512MB per container

### Monitoring
- **Application Logs**: `docker-compose logs -f`
- **Database Queries**: Laravel Telescope integration
- **3D Performance**: Custom performance profiler
- **P2P Network**: Real-time connection monitoring

## 🔒 Security

### Authentication
- **Passkey**: FIDO2/WebAuthn with cryptographic signatures
- **Session Management**: Redis-based with secure cookies
- **Rate Limiting**: Built-in Laravel throttling

### Data Protection
- **Encryption**: AES-256 for all sensitive data
- **Zero-Knowledge**: Domicile data never exposed without consent
- **Audit Trail**: Complete reputation transaction history

### Network Security
- **P2P Encryption**: End-to-end WebRTC encryption
- **Signaling**: Secure WebSocket connections with Reverb
- **Identity**: Cryptographic proof verification

## 📚 Documentation

- **Architecture**: `/docs/AI-Proposals/` - Technical design documents
- **API**: `/docs/api/` - RESTful API documentation  
- **History**: `/docs/history/CIV_LOG.md` - Development chronology
- **Roadmap**: `/docs/4-Week-Roadmap.md` - Development timeline

## 🤝 Contributing

### Development Workflow
1. Create feature branch from `develop`
2. Implement changes with tests
3. Run full test suite
4. Submit pull request with SR&ED documentation

### Code Standards
- **PHP**: Follow PSR-12 coding standards
- **Dart**: Follow official Dart style guide
- **Documentation**: Comprehensive inline comments
- **Testing**: Minimum 80% code coverage

## 📄 License

This project is licensed under the MIT License - see LICENSE file for details.

## 🙏 Acknowledgments

- **Protocol Labs**: libp2p P2P networking library
- **Laravel Team**: Modern PHP framework
- **Dart Team**: High-performance Wasm compilation
- **WebRTC Community**: Real-time communication standards

---

**The Village** - Building trust-based communities through decentralized technology and cryptographic reputation systems.

## About Me
I am a polyglot developer with 20+ years of experience in system architecture, building a low overhead, ethically coded alternative to intrusive platforms.

## Reasoning
Why do we need another social network?
Because existing social networks are built on surveillance, conflict and data harvesting. 

I would like to build one that encourages people to be better.

I believe that social media is not just a reflection of society, but a tool that shapes it. I want to build a social network that is built on trust and community. I want to build a social network that not only allows you to offer your neighbor a shovel, but also rewards you for it. 

In my vision each individual has a reputation score based on their actions and contributions to the community. This score represents their trust and community standing. On a wider scope a entire village can have a reputation score based on their actions and contributions to other villages. and on a hardware level each server can have a reputation score based on their actions and contributions to the network.

## Stack
- Low cost LAMP hosting
- Laravel (13)
- Dart (3) compiled to webasm
Built on low cost hosting, with built in p2p resource sharing, makes the server extreemly scalable. it also means the more servers the faster and more reliable the network becomes.
The frontend is built with Dart and compiled to webasm for maximum performance while rendering a 3d UI. 

## 1 Month Goal

3 websites (for p2p seeding):They are built on attention and engagement. They are built on profit. They are not built on trust and community.
- The Village (main website)
- beatpoetmassive.com (an artist collective website)
- allysonvollmer.com (A singlepage website for an RMT)

3 Village with 5 denizens each.
3 instances of successful p2p resource sharing

## How it works:
The village is a social network

You start out as a stranger and only when you are vouched for by a denizen of a village can you post or comment in the village.

As you participate in the village (sharing commenting etc) you build trust. When you are found to be false you loose trust. If you are proven to be acting in bad faith, you have committed betrayal and your reputation is damaged (other users can see this). Trust also erodes over time.

A stranger who is vouched for (another user has staked some of their reputation on them being trustworthy) becomes a sojourner. A sojourner who builds some trust in the village becomes a denizen.

A denizen can rise to a steward (a moderator) and eventually an Elder (sets guidelines for the village).

When a village gets to large or a petition is recieved for a new village, an elder can vouch (stake some of their reputation) for the new village.

An Elder who has successfully (after a certain time the reputation is returned with a bonus) started at least one new village can become a Reeve (can set guidelines for a groups of villages).

When you join you can use any name you want. One of the ways you can develop trust is to verify your identity, it is not required, and won't prevent you from becoming a denizen but it will be required for stewards and higher roles.

### The gateway
The gateway is the public webpage
It contains village showcases and even live broadcasts. There is a tavern when you can meet users (as a stranger) and even get vouched into a village.

### The map
The map is a 3d representation of all of the villages. The positions of the villages will be based on the strength of the relationships between villages.

### The Village
The village is a 3d space where sojourners and denizens interact

#### Size
A village should never get bigger than 3 degrees of separation. So that means that if a denizen is say looking for a resource they should be able to ask a denizen who knows a denizen.

#### Places in the village:
The bulletin board - village wide posts
The hall - event announcements, village guide lines and duties, forums, global show case
The Commons - shared resources, village wide or global
The Guild Hall - denizens showcase their creations, create collaborative relationships, and trade skills, supplies and resources.

### The Domicile

This is the user's personal space.

It can contain feeds from and entrances to different villages (yes you can join more than one village), message windows (video/audio/text), feeds from social media, news, aggregated feeds, content creation tools (music, art, video) that don't just help you record they help you plan and with logistics and brand tracking. All of your personal stuff is here.

### Reputation

#### Trust
Trust is how we measure everything. Trust is calculated for users as well as everything else in the system. We can calculate a value for how much we trust a file or resource on the Internet or how much we can trust a village.

Reputations are based on the context that they are calculated in. For instance, how much you are trusted in your village is not the same as how much you are trusted in the whole map. So you would have different reputation scores based on the context.

Your personal integrity score is the baseline of how much you can be trusted.

#### Vouching
A user can vouch for things which means stake some of their integrity on something. This depletes their integrity. Since roles are based on integrity a user could, for instance, loose their status as a Steward for vouching for a stranger.

Integrity is restored if the touched for thing achieved baseline integrity (so a stranger becomes a denizen for example). If it was trustworthy (baseline integrity) then the user receives a 20% bonus on their integrity.

#### Accountability
Bad faith equals a hit to reputation.

#### Reconciliation
Denizens can forgive you

#### Breeches
A breech is a failure to deliver on a promise. The cost of this to integrity is dependent on the value of the promise.

#### Betrayals
A betrayal is very damaging to trust.

#### Time
Integrity erodes over time. Inertia is a factor. So a user must remain active and trustworthy.

### Roles

Stranger
Sojourner
Denizen
Steward - moderates
Elder - runs village
Reeve - runs many villages (on a council)
High Reeve - runs council